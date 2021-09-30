<?php

namespace App\Controller;

use App\Utils\Uuid;
use App\Entity\User;
use App\Form\UserType;
use App\Utils\Gravatar;
use App\Form\UserProfileType;
use App\Form\UserPasswordType;
use App\Repository\UserRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\Common\Collections\Criteria;
use App\Service\ProfilePictureFileUploader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Validator\Constraints\Email;
use App\Service\VPN\VPNConfiguratorGeneratorInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends Controller
{
    protected $passwordEncoder;
    protected $userRepository;
    protected $mailer;
    protected $serializer;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, UserRepository $userRepository, \Swift_Mailer $mailer, SerializerInterface $serializer)
    {
        $this->passwordEncoder = $passwordEncoder;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/admin/users", name="users", methods={"GET", "POST"})
     * 
     * @Rest\Get("/api/users", name="api_users")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);
        $role = $request->query->get('role');
        $orderBy = $request->query->get('orderBy', 'lastName');
        $orderDirection = $request->query->get('orderDirection', 'ASC');

        // handle incorrect orderBy field
        if (!property_exists(User::class, $orderBy)) {
            $orderBy = 'lastName';
        }

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('firstName', $search))
            ->orWhere(Criteria::expr()->contains('lastName', $search))
            ->orWhere(Criteria::expr()->contains('email', $search))
            ->orderBy([
                $orderBy => $orderDirection
            ]);

        $users = $this->userRepository->matching($criteria);
        $count = $users->count();
        $adminCount = $users->filter(function ($user) {
            return $user->getHighestRole() === 'ROLE_ADMINISTRATOR' || $user->getHighestRole() === 'ROLE_SUPER_ADMINISTRATOR';
        })->count();
        $teacherCount = $users->filter(function ($user) {
            return $user->getHighestRole() === 'ROLE_TEACHER';
        })->count();
        $studentCount = $users->filter(function ($user) {
            return $user->getHighestRole() === 'ROLE_USER';
        })->count();

        if ($role) {
            switch ($role) {
                case 'admin':
                    $users = $users->filter(function ($user) {
                        return $user->getHighestRole() === 'ROLE_ADMINISTRATOR' || $user->getHighestRole() === 'ROLE_SUPER_ADMINISTRATOR';
                    });
                break;
                case 'teacher':
                    $users = $users->filter(function ($user) {
                        return $user->getHighestRole() === 'ROLE_TEACHER';
                    });
                break;
                
                case 'student':
                    $users = $users->filter(function ($user) {
                        return $user->getHighestRole() === 'ROLE_USER';
                    });
                break;
            }
        }

        $addUserFromFileForm = $this->createFormBuilder([])
            ->add('file', FileType::class, [
                "help" => "Accepted formats: csv",
                "attr" => [
                    "accepted" => ".csv",
                ]
            ])
            ->add('submit', SubmitType::class)
            ->getForm();

        $addUserFromFileForm->handleRequest($request);

        if ($addUserFromFileForm->isSubmitted() && $addUserFromFileForm->isValid()) {
            $file = $addUserFromFileForm->getData()['file'];

            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (in_array($fileExtension, ['csv'])) {
                $fileSocket = fopen($file, 'r');

                $addedUsers = [];

                switch ($fileExtension) {
                    case 'csv':
                        $addedUsers = $this->createUserFromCSV($fileSocket);
                        break;
                }

                if (count($addedUsers) > 0) {
                    $this->addFlash('success', 'Utilisateur(s) créé(s).');
                } else {
                    $this->addFlash(
                        'warning',
                        'Aucun utilisateur créé. Veuillez vérifier que les
                        utilisateurs spécifiés dans le fichier n\'existent pas déjà ou que le format du fichier est correct.'
                    );
                }

                fclose($fileSocket);
            } else {
                $this->addFlash('danger', "Ce type de fichier n'est pas accepté.");
            }
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($users->slice($page * $limit - $limit, $limit), 200, [], [$request->get('_route')]);
        }

        return $this->render('user/index.html.twig', [
            'users' => $users->slice($page * $limit - $limit, $limit),
            'addUserFromFileForm' => $addUserFromFileForm->createView(),
            'search' => $search,
            'count' => [
                'total' => $count,
                'current' => $users->count(),
                'admins' => $adminCount,
                'teachers' => $teacherCount,
                'students' => $studentCount
            ],
            'limit' => $limit,
            'page' => $page,
        ]);
    }

    /**
     * @Rest\Get("/api/users/{id<\d+>}", name="api_get_user")
     */
    public function showAction(Request $request, int $id)
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new NotFoundHttpException();
        }

        return $this->json($user, 200, [], [$request->get('_route')]);
    }

    /**
     * @Route("/admin/users/new", name="new_user", methods={"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $user = new User();
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            /** @var User $user */
            $user = $userForm->getData();

            $user->setRoles([$userForm->get('roles')->getData()]);

            $password = $userForm->get('password')->getData();
            $confirmPassword = $userForm->get('confirmPassword')->getData();


            if (!$password) {
                $this->addFlash('danger', "You must provide a password.");
            } elseif ($password === $confirmPassword) {
                $user->setPassword($this->passwordEncoder->encodePassword($user, $password));
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($user);
                $entityManager->flush();

                $this->sendNewAccountEmail($user, $password);

                $this->addFlash('success', 'User has been created.');

                return $this->redirectToRoute('users');
            } else {
                $this->addFlash('danger', "Passwords doesn't match.");
            }
        }

        return $this->render('user/new.html.twig', [
            'userForm' => $userForm->createView()
        ]);
    }

    /**
     * @Route("/admin/users/{id<\d+>}/edit", name="edit_user", methods={"GET", "POST"})
     */
    public function editAction(Request $request, int $id)
    {
        $user = $this->userRepository->find($id);

        if (null === $user) {
            throw new NotFoundHttpException();
        }

        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            /** @var User $user */
            $user = $userForm->getData();
            $roles[] = $userForm->get('roles')->getData();
            $user->setRoles($roles);
            $password = $userForm->get('password')->getData();
            $confirmPassword = $userForm->get('confirmPassword')->getData();

            if ($password) {
                if ($password === $confirmPassword) {
                    $user->setPassword($this->passwordEncoder->encodePassword($user, $password));
                } else {
                    $this->addFlash('danger', "Passwords doesn't match. If you don't want to change user's password, please leave password field empty.");

                    return $this->redirectToRoute('edit_user', ['id' => $id]);
                }
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User has been edited.');

            return $this->redirectToRoute('users');
        }

        return $this->render('user/new.html.twig', [
            'userForm' => $userForm->createView(),
            'user' => $user
        ]);
    }

    /**
     * @Route("/admin/users/{id<\d+>}/toggle", name="toggle_user", methods="GET")
     * 
     * @Rest\Patch("/api/users/{id<\d+>}", name="api_toggle_user")
     */
    public function toggleAction(Request $request, $id)
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new NotFoundHttpException('This user does not exist.');
        } elseif ($user == $this->getUser()) {
            throw new UnauthorizedHttpException('You cannot lock your own account.');
        } elseif ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
            throw new UnauthorizedHttpException('You cannot lock root account.');
        } else {
            $user->setEnabled(!$user->isEnabled());

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $user->getName() . "'s account has been locked.");

        return $this->redirectToRoute('users');
    }

    /**
     * @Route("/admin/users/{id<\d+>}", name="delete_user", methods={"GET", "DELETE"})
     * 
     * @Rest\Delete("/api/users/{id<\d+>}", name="api_delete_user")
     */
    public function deleteAction(Request $request, $id)
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new NotFoundHttpException('This user does not exist.');
        } elseif ($user == $this->getUser()) {
            throw new UnauthorizedHttpException('You cannot delete your own account.');
        } elseif ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
            throw new UnauthorizedHttpException('You cannot delete root account.');
        } elseif ($user->getInstances()->count() > 0) {
            throw new UnauthorizedHttpException('You cannot delete an user who still has instances. Please stop them and try again.');
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $user->getName() . "'s account has been deleted.");

        return $this->redirectToRoute('users');
    }

    /**
     * Format du tableau :
     * Nom,Prénom,Mail,Pass
     * @return array The number of elements added
     */
    public function createUserFromCSV($file)
    {
        $i = 0;
        $line = array();
        $addedUsers = array();
        $validator = Validation::createValidator();
        $entityManager = $this->getDoctrine()->getManager();

        while ($line[$i] = fgets($file, 4096)) {
            $line[$i] = str_replace('"', '', $line[$i]);

            $data = explode(",", $line[$i]);

            if (count($data) === 4) {
                $lastName = $data[0];
                $firstName = $data[1];
                $email = $data[2];
                $password = trim($data[3]); // trim newline because this is the last field

                $user = new User();

                $user
                    ->setLastName($firstName)
                    ->setFirstName($lastName)
                    ->setEmail($email)
                    ->setPassword($this->passwordEncoder->encodePassword($user, $password));

                $validEmail = count($validator->validate($email, [new Email()])) === 0;

                if ($validEmail && $this->userRepository->findByEmail($email) == null) {
                    $entityManager->persist($user);
                    $this->sendNewAccountEmail($user, $password);
                    $addedUsers[$i] = $user;
                }
            }

            $i++;
        }

        $entityManager->flush();

        return $addedUsers;
    }

    /**
     * Send an email to a user with his password.
     *
     * @param User $user
     * @return void
     */
    private function sendNewAccountEmail($user, $password)
    {
        $message = (new \Swift_Message('Your RemoteLabz account'))
            ->setFrom('remotelabz@remotelabz.univ-reims.fr')
            ->setTo($user->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/new_user.html.twig',
                    [
                        'name' => $user->getName(),
                        'link' => $this->generateUrl('login', [],  UrlGeneratorInterface::ABSOLUTE_URL),
                        'password' => $password
                    ]
                ),
                'text/html'
            );

        $this->mailer->send($message);
    }

    /**
     * @Route("/profile", name="user_profile")
     */
    public function profileAction(Request $request)
    {
        $user = $this->getUser();
        $userForm = $this->createForm(UserProfileType::class, $user);
        $passwordForm = $this->createForm(UserPasswordType::class, new User());
        $userForm->handleRequest($request);
        $passwordForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $user = $userForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated.');
        }

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $password = $passwordForm->get('password')->getData();
            $newPassword = $passwordForm->get('newPassword')->getData();
            $confirmPassword = $passwordForm->get('confirmPassword')->getData();

            if ($this->passwordEncoder->isPasswordValid($user, $password)) {
                if ($newPassword == $confirmPassword) {
                    $user->setPassword($this->passwordEncoder->encodePassword($user, $newPassword));

                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Your password has been changed.');

                    return $this->redirectToRoute('user_profile');
                } else {
                    $this->addFlash('danger', "Passwords doesn't match.");
                }
            } else {
                $this->addFlash('danger', 'Your password is incorrect.');
            }
        }

        return $this->render('user/profile.html.twig', [
            'user' => $this->getUser(),
            'userForm' => $userForm->createView(),
            'passwordForm' => $passwordForm->createView()
        ]);
    }

    /**
     * @Route("/profile/picture", name="post_user_profile_picture", methods="POST")
     */
    public function profilePictureAction(Request $request, ProfilePictureFileUploader $fileUploader)
    {
        $user = $this->getUser();

        $pictureFile = $request->files->get('picture');
        if ($pictureFile) {
            $pictureFileName = $fileUploader->upload($pictureFile);
            $user->setProfilePictureFilename($pictureFileName);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('user_profile');
    }

    /**
     * @Route("/profile/picture", name="get_current_user_profile_picture", methods="GET")
     */
    public function getProfilePictureAction(Request $request, KernelInterface $kernel)
    {
        $user = $this->getUser();
        $size = $request->query->get('size', 128);

        $profilePicture = $user->getProfilePicture();

        if ($profilePicture && is_file($profilePicture)) {
            $image = file_get_contents($profilePicture);
            $image = imagecreatefrompng($profilePicture);
            $image = imagescale($image, $size, $size, IMG_GAUSSIAN);
            $imageTmp = $kernel->getCacheDir() . "/" . new Uuid();
            $image = imagepng($image, $imageTmp, 9);

            $response = new Response(file_get_contents($imageTmp), 200);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="' . $user->getProfilePictureFilename() . '"');

            return $response;
        } else {
            //TODO #644
            $picture = file_get_contents(Gravatar::getGravatar($user->getEmail(), $size));

            return new Response($picture, 200, ['Content-Type' => 'image/jpeg']);
        }
    }

    /**
     * @Route("/users/{id<\d+>}/picture", name="get_user_profile_picture", methods="GET")
     */
    public function getUserProfilePictureAction(Request $request, int $id, KernelInterface $kernel)
    {
        $user = $this->userRepository->find($id);
        $size = $request->query->get('size', 128);

        $profilePicture = $user->getProfilePicture();

        if ($profilePicture && is_file($profilePicture)) {
            $cachedImagePath = $kernel->getCacheDir() . "/users/avatar/" . $user->getId() . "/" . $size;

            if (!is_file($cachedImagePath)) {
                $image = file_get_contents($profilePicture);
                $image = imagecreatefrompng($profilePicture);
                $image = imagescale($image, $size, $size, IMG_GAUSSIAN);
                $imageTmp = $cachedImagePath;
                if (!is_dir($kernel->getCacheDir() . "/users/avatar/" . $user->getId())) {
                    mkdir($kernel->getCacheDir() . "/users/avatar/" . $user->getId(), 0777, true);
                }
                imagepng($image, $imageTmp, 9);
            }

            $response = new Response(file_get_contents($cachedImagePath), 200);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="' . $user->getProfilePictureFilename() . '"');

            return $response;
        } else {
            $picture = file_get_contents(Gravatar::getGravatar($user->getEmail(), $size));

            return new Response($picture, 200, ['Content-Type' => 'image/jpeg']);
        }
    }

    /**
     * @Route("/profile/picture", name="delete_user_profile_picture", methods="DELETE")
     */
    public function deleteProfilePictureAction(Request $request, KernelInterface $kernel)
    {
        $user = $this->getUser();
        $size = $request->query->get('size', 128);

        $profilePicture = $user->getProfilePicture();

        if ($profilePicture && is_file($profilePicture)) {
            $image = file_get_contents($profilePicture);
            $image = imagecreatefrompng($profilePicture);
            $image = imagescale($image, $size, $size, IMG_BILINEAR_FIXED);
            $imageTmp = $kernel->getCacheDir() . "/" . new Uuid();
            $image = imagepng($image, $imageTmp, 9);

            $response = new Response(file_get_contents($imageTmp), 200);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="' . $user->getProfilePictureFilename() . '"');

            return $response;
        } else {
            $picture = file_get_contents(Gravatar::getGravatar($user->getEmail(), $size));

            return new Response($picture, 200, ['Content-Type' => 'image/jpeg']);
        }
    }

    /**
     * @Rest\Get("/api/users/me", name="api_users_me")
     */
    public function meAction()
    {
        return $this->redirectToRoute('api_get_user', ['id' => $this->getUser()->getId()]);
    }

    /** 
     * @Route("/profile/vpn", name="get_user_vpn_config", methods="GET")
     */
    public function vpnConfigurationGenerateAction(VPNConfiguratorGeneratorInterface $VPNConfigurationGenerator)
    {
        $user = $this->getUser();
        $x509 = null;
        $privateKey = null;
        $certsDir = $VPNConfigurationGenerator->getExportPath();
        $certPath = $certsDir.'/'.$user->getUsername().'.crt';
        $pkeyPath = $certsDir.'/'.$user->getUsername().'.key';
        $filesystem = new Filesystem();

        if (!$filesystem->exists($certPath))
        {
            $VPNConfigurationGenerator->generate($privateKeyResource, $x509Resource);
            openssl_x509_export_to_file($x509Resource, $certPath);
            openssl_pkey_export_to_file($privateKeyResource, $pkeyPath);
            openssl_x509_export($x509Resource, $x509);
            openssl_pkey_export($privateKeyResource, $privateKey);
        } else {
            $x509 = file_get_contents($certPath);
            $privateKey = file_get_contents($pkeyPath);
        }

        $config = $VPNConfigurationGenerator->generateConfig($privateKey, $x509);

        $response = new Response($config);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $user->getUsername().'.ovpn'
        );

        $response->headers->set('Content-Type', 'application/x-openvpn-profile');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
