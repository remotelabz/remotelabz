<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;

use App\Form\UserProfileType;
use App\Form\UserPasswordType;
use App\Controller\AppController;
use App\Repository\UserRepository;
use JMS\Serializer\SerializerBuilder;
use Symfony\Bundle\MakerBundle\Validator;
use App\Service\ProfilePictureFileUploader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends AppController
{
    public $passwordEncoder;

    public $userRepository;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, UserRepository $userRepository)
    {
        $this->passwordEncoder = $passwordEncoder;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/admin/users", name="users", methods={"GET", "POST"})
     */
    public function indexAction(Request $request)
    {
        $user = new User();

        $addUserForm = $this->createForm(UserType::class, $user);
        $addUserFromFileForm = $this->createFormBuilder([])
            ->add('file', FileType::class, [
                "help" => "Accepted formats: csv"
            ])
            ->add('submit', SubmitType::class)
            ->getForm();

        $addUserForm->handleRequest($request);
        $addUserFromFileForm->handleRequest($request);

        if ($addUserForm->isSubmitted() && $addUserForm->isValid()) {
            /** @var User $user */
            $user = $addUserForm->getData();
            $confirmPassword = $addUserForm->get('confirmPassword')->getData();

            if ($user->getPassword() === $confirmPassword) {
                $user->setPassword($this->passwordEncoder->encodePassword($user, $user->getPassword()));

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'User has been created.');
            } else {
                $this->addFlash('danger', "Passwords doesn't match.");
            }
        } elseif ($addUserFromFileForm->isSubmitted() && $addUserFromFileForm->isValid()) {
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
                        utilisateurs spécifiés dans le fichier n\'existent pas déjà.'
                    );
                }
                
                fclose($fileSocket);
            } else {
                $this->addFlash('danger', "Ce type de fichier n'est pas accepté.");
            }
        }

        return $this->render('user/index.html.twig', [
            'addUserForm' => $addUserForm->createView(),
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
            $password = $userForm->get('password')->getData();
            $confirmPassword = $userForm->get('confirmPassword')->getData();

            if (!$password) {
                $this->addFlash('danger', "You must provide a password.");
            } elseif ($password === $confirmPassword) {
                $user->setPassword($this->passwordEncoder->encodePassword($user, $password));

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($user);
                $entityManager->flush();

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
     * @Route("/admin/users/{id<\d+>}/toggle", name="toggle_user", methods="PATCH")
     */
    public function toggleAction($id)
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

        return $this->renderJson($data, $status);
    }

    /**
     * @Route("/admin/users/{id<\d+>}", name="delete_user", methods="DELETE")
     */
    public function deleteAction($id)
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

        return $this->renderJson($data, $status);
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

                $addedUsers[$i] = $user;
            }

            $i++;
        }

        $entityManager->flush();

        return $addedUsers;
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
}
