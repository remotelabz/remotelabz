<?php

namespace App\Controller;

use App\Utils\Uuid;
use App\Entity\User;

use App\Form\UserType;
use App\Utils\Gravatar;
use App\Form\UserProfileType;
use App\Form\UserPasswordType;
use Swagger\Annotations as SWG;
use App\Controller\AppController;
use App\Repository\UserRepository;
use JMS\Serializer\SerializerBuilder;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Bundle\MakerBundle\Validator;
use App\Service\ProfilePictureFileUploader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\Email;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends AbstractFOSRestController
{
    public $passwordEncoder;

    public $userRepository;

    public $mailer;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, UserRepository $userRepository, \Swift_Mailer $mailer)
    {
        $this->passwordEncoder = $passwordEncoder;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
    }

    /**
     * @Route("/admin/users", name="users", methods={"GET", "POST"})
     */
    /**
     * @Route("/admin/users", name="users", methods={"GET", "POST"})
     * 
     * @Rest\Get("/api/users", name="api_users")
     * 
     * @SWG\Parameter(
     *     name="search",
     *     in="query",
     *     type="string",
     *     description="Filter users by name. All users with a name containing this value will be shown."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns all existing users",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Lab::class))
     *     )
     * )
     * 
     * @SWG\Tag(name="User")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $limit = $request->query->get('limit', 10);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('firstName', $search))
            ->orWhere(Criteria::expr()->contains('lastName', $search))
            ->orWhere(Criteria::expr()->contains('email', $search))
            ->orderBy([
                'lastName' => Criteria::ASC
            ])
            ->setMaxResults($limit)
        ;

        $users = $this->userRepository->matching($criteria);

        $addUserFromFileForm = $this->createFormBuilder([])
            ->add('file', FileType::class, [
                "help" => "Accepted formats: csv"
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

        $view = $this->view($users->getValues())
            ->setTemplate("user/index.html.twig")
            ->setTemplateData([
                'users' => $users,
                'addUserFromFileForm' => $addUserFromFileForm->createView(),
                'search' => $search
            ])
        ;

        return $this->handleView($view);

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'addUserFromFileForm' => $addUserFromFileForm->createView(),
        ]);
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
            
            foreach ($userForm->get('roles') as $role)
                    $user->setRoles($role);

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
            $password = $userForm->get('password')->getData();
            $confirmPassword = $userForm->get('confirmPassword')->getData();

            if ($password) {
                if ($password === $confirmPassword) {
                    $user->setPassword($this->passwordEncoder->encodePassword($user, $password));
                } else {
                    $this->addFlash('danger', "Passwords doesn't match. If you don't want to change user's password, please leave password field empty.");

                    return $this->redirectToRoute('edit_user', [ 'id' => $id ]);
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
     * @Route("/users", name="get_users", methods="GET")
     */
    public function cgetAction()
    {
        return $this->renderJson($this->userRepository->findAll());
    }

    /**
     * @Route("/admin/users/{id<\d+>}/toggle", name="toggle_user", methods={"GET", "PATCH"})
     */
    public function toggleAction(Request $request, $id)
    {
        $status = 200;
        $data = [];

        $user = $this->userRepository->find($id);

        if ($user == null) {
            throw new NotFoundHttpException('This user does not exist.');
        } elseif ($user == $this->getUser()) {
            $status = 403;
            $data['message'] = 'You cannot lock your own account.';
        } elseif ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
            // Prevent super admin deletion
            $status = 403;
            $data['message'] = 'You cannot lock root account.';
        } else {
            $user->setEnabled(!$user->isEnabled());

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $data['message'] = 'User has been ' . ($user->isEnabled() ? 'enabled' : 'disabled') . '.';
        }

        if ($request->getRequestFormat() === 'json') {
            return $this->renderJson($data, $status);
        } else {
            $this->addFlash($status < 400 ? 'success' : 'danger', $data['message']);

            return $this->redirectToRoute('users');
        }
    }

    /**
     * @Route("/admin/users/{id<\d+>}", name="delete_user", methods={"GET", "DELETE"})
     */
    public function deleteAction(Request $request, $id)
    {
        $status = 200;
        $data = [];
        
        $user = $this->userRepository->find($id);

        if ($user == null) {
            $status = 404;
        } elseif ($user == $this->getUser()) {
            $status = 403;
            $data['message'] = 'You cannot delete your own account.';
        } elseif ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
            // Prevent super admin deletion
            $status = 403;
            $data['message'] = 'You cannot delete root account.';
        } elseif ($user->getInstances()->count() > 0) {
            $status = 403;
            $data['message'] = 'You cannot delete an user who still has instances. Please stop them and try again.';
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();

            $data['message'] = 'User has been deleted.';
        }

        if ($request->getRequestFormat() === 'json') {
            return $this->renderJson($data, $status);
        } else {
            $this->addFlash($status < 400 ? 'success' : 'danger', $data['message']);

            return $this->redirectToRoute('users');
        }
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
                    ->setPassword($this->passwordEncoder->encodePassword($user, $password))
                ;

                $validEmail = count($validator->validate($email, [ new Email() ])) === 0;
    
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
            )
        ;

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
    public function getProfilePictureAction(Request $request)
    {
        $user = $this->getUser();
        $size = $request->query->get('size', 128);

        $profilePicture = $user->getProfilePicture();

        if ($profilePicture && is_file($profilePicture)) {
            $image = file_get_contents($profilePicture);
            $image = imagecreatefrompng($profilePicture);
            $image = imagescale($image, $size, $size, IMG_GAUSSIAN);
            $imageTmp = "/tmp/" . new Uuid();
            $image = imagepng($image, $imageTmp, 9);

            $response = new Response(file_get_contents($imageTmp), 200);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="'.$user->getProfilePictureFilename().'"');

            return $response;
        } else {
            $picture = file_get_contents(Gravatar::getGravatar($user->getEmail(), $size));

            return new Response($picture, 200, [ 'Content-Type' => 'image/jpeg' ]);
        }
        // $pictureFile = $request->files->get('picture');
        // if ($pictureFile) {
        //     $pictureFileName = $fileUploader->upload($pictureFile);
        //     $user->setProfilePictureFilename($pictureFileName);
        // }

        // $entityManager = $this->getDoctrine()->getManager();
        // $entityManager->persist($user);
        // $entityManager->flush();

        // return $this->redirectToRoute('user_profile');
    }

    /**
     * @Route("/users/{id<\d+>}/picture", name="get_user_profile_picture", methods="GET")
     */
    public function getUserProfilePictureAction(Request $request, int $id)
    {
        $user = $this->userRepository->find($id);
        $size = $request->query->get('size', 128);

        $profilePicture = $user->getProfilePicture();

        if ($profilePicture && is_file($profilePicture)) {
            $image = file_get_contents($profilePicture);
            $image = imagecreatefrompng($profilePicture);
            $image = imagescale($image, $size, $size, IMG_GAUSSIAN);
            $imageTmp = "/tmp/" . new Uuid();
            imagepng($image, $imageTmp, 9);

            $response = new Response(file_get_contents($imageTmp), 200);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="'.$user->getProfilePictureFilename().'"');

            return $response;
        } else {
            $picture = file_get_contents(Gravatar::getGravatar($user->getEmail(), $size));

            return new Response($picture, 200, [ 'Content-Type' => 'image/jpeg' ]);
        }
    }

    /**
     * @Route("/profile/picture", name="delete_user_profile_picture", methods="DELETE")
     */
    public function deleteProfilePictureAction(Request $request)
    {
        $user = $this->getUser();
        $size = $request->query->get('size', 128);

        $profilePicture = $user->getProfilePicture();

        if ($profilePicture && is_file($profilePicture)) {
            $image = file_get_contents($profilePicture);
            $image = imagecreatefrompng($profilePicture);
            $image = imagescale($image, $size, $size, IMG_BILINEAR_FIXED);
            $imageTmp = "/tmp/" . new Uuid();
            $image = imagepng($image, $imageTmp, 9);

            $response = new Response(file_get_contents($imageTmp), 200);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="'.$user->getProfilePictureFilename().'"');

            return $response;
        } else {
            $picture = file_get_contents(Gravatar::getGravatar($user->getEmail(), $size));

            return new Response($picture, 200, [ 'Content-Type' => 'image/jpeg' ]);
        }
        // $pictureFile = $request->files->get('picture');
        // if ($pictureFile) {
        //     $pictureFileName = $fileUploader->upload($pictureFile);
        //     $user->setProfilePictureFilename($pictureFileName);
        // }

        // $entityManager = $this->getDoctrine()->getManager();
        // $entityManager->persist($user);
        // $entityManager->flush();

        // return $this->redirectToRoute('user_profile');
    }
}
