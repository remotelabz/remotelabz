<?php

namespace App\Controller;

use Exception;
use App\Utils\Uuid;
use App\Entity\User;
use App\Entity\Group;
use App\Form\UserType;
use App\Utils\Gravatar;
use App\Form\UserProfileType;
use App\Form\UserPasswordType;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Service\VPN\VPNConfiguratorGeneratorInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Doctrine\Common\Collections\Criteria;
use App\Service\ProfilePictureFileUploader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as Email;
use Symfony\Component\Validator\Constraints\Email as ConstraintsEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;

class UserController extends Controller
{
    protected $passwordHasher;
    protected $userRepository;
    protected $mailer;
    protected $serializer;
     
    /** @var LoggerInterface $logger */
     private $logger;

    public function __construct(
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        GroupRepository $groupRepository,
        MailerInterface $mailer,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        string $url_check_internet,
        string $remotevpn_addr,
        string $contact_mail,
        EntityManagerInterface $entityManager)
    {
        $this->passwordHasher = $passwordHasher;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
        $this->mailer = $mailer;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->url_check_internet = $url_check_internet;
        $this->remotevpn_addr = $remotevpn_addr;
        $this->contact_mail = $contact_mail;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/users', name: 'api_users')]
	#[IsGranted("ROLE_TEACHER", message: "Access denied.")]
    #[Route(path: '/admin/users', name: 'users', methods: ['GET', 'POST'])]
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
        $teacherEditorCount = $users->filter(function ($user) {
            return $user->getHighestRole() === 'ROLE_TEACHER_EDITOR';
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
                case 'editor':
                    $users = $users->filter(function ($user) {
                        return $user->getHighestRole() === 'ROLE_TEACHER_EDITOR';
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
                        $addedUsers = $this->createUserFromCSV($fileSocket,$file);
                        break;
                }

                if ($addedUsers && count($addedUsers) > 0) {
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
            return $this->redirectToRoute('users');

        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($users->slice($page * $limit - $limit, $limit), 200, [], [$request->get('_route')]);
        }

        $currentUser=$this->serializer->serialize(
            $this->getUser(),
            'json',
            SerializationContext::create()->setGroups(['api_users'])
        );
        return $this->render('user/index.html.twig', [
            'users' => $users->slice($page * $limit - $limit, $limit),
            'currentUser' => $currentUser,
            'addUserFromFileForm' => $addUserFromFileForm->createView(),
            'search' => $search,
            'count' => [
                'total' => $count,
                'current' => $users->count(),
                'admins' => $adminCount,
                'teacherEditors' => $teacherEditorCount,
                'teachers' => $teacherCount,
                'students' => $studentCount
            ],
            'limit' => $limit,
            'page' => $page,
        ]);
    }

    
    public function fetchUsersAction(Request $request)
    {
        $users = $this->userRepository->findAll();
        usort($users, function ($a,$b) {return strcmp($a->getLastName(), $b->getLastName());});

        if ('json' === $request->getRequestFormat()) {
            return $this->json($users, 200, [], ["api_users"]);
        }

    }

    /*    /*public function fetchUserTypeByGroupOwner(Request $request, string $userType, int $id)
    {
        $owner = $this->userRepository->find($id);
        $users = $this->userRepository->findUserTypesByGroups($userType, $owner);

        if (!$users) {
            throw new NotFoundHttpException();
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($users, 200, [], ["api_users"]);
        }

    }*/

    
	#[Get('/api/users/{id<\d+>}', name: 'api_get_user')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function showAction(Request $request, int $id)
    {
        $user = $this->userRepository->find($id);
        $currentUser = $this->getUser();

        if (!$user) {
            throw new NotFoundHttpException();
        }

        if (!$currentUser->isAdministrator() && $user !== $currentUser) {
            throw new AccessDeniedHttpException("Access denied.");
        }

        return $this->json($user, 200, [], [$request->get('_route')]);
    }

    #[Route(path: '/admin/users/new', name: 'new_user', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $user = new User();
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            /** @var User $user */
            $user = $userForm->getData();

            $user->setRoles([$userForm->get('roles')->getData()]);

            $default_group=$this->groupRepository->findOneByName('Default group');

            $default_group->addUser($user);

            $password = $userForm->get('password')->getData();
            $confirmPassword = $userForm->get('confirmPassword')->getData();
            if (!$password) {
                $this->addFlash('danger', "You must provide a password.");
            } elseif ($password === $confirmPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                $entityManager = $this->entityManager;
            try {
                $entityManager->persist($user);
                $entityManager->flush();
            
                try {
                    $this->sendNewAccountEmail($user, $password);
                    $this->addFlash('success', 'User has been created.');
                }
                catch (Exception $e) {
                    $this->logger->debug("Mail error :".$e);
                    $this->logger->info("Mail error :".$e->getMessage());
                    $this->addFlash('danger', 'No mail send - Error in mailer configuration');
                }

                return $this->redirectToRoute('users');
            }
            catch (Exception $e) {
                $this->logger->debug("Creation user is impossible :".$e);
                $this->logger->info("Creation user is impossible.".$e->getMessage());
                $this->addFlash('danger', 'User is not created');

            }
            } else {
                $this->addFlash('danger', "Passwords doesn't match.");
            }
        }

        return $this->render('user/new.html.twig', [
            'userForm' => $userForm->createView()
        ]);
    }

    #[Route(path: '/admin/users/{id<\d+>}/edit', name: 'edit_user', methods: ['GET', 'POST'])]
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
                    $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                } else {
                    $this->addFlash('danger', "Passwords doesn't match. If you don't want to change user's password, please leave password field empty.");

                    return $this->redirectToRoute('edit_user', ['id' => $id]);
                }
            }

            $entityManager = $this->entityManager;
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

    
	#[Patch('/api/users/{id<\d+>}', name: 'api_toggle_user')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/users/{id<\d+>}/toggle', name: 'toggle_user', methods: 'GET')]
    public function toggleAction(Request $request, $id)
    {
        $user = $this->userRepository->find($id);
        $data = json_decode($request->getContent(), true);

        if (!$user) {
            throw new NotFoundHttpException('This user does not exist.');
        } elseif ($user == $this->getUser()) {
            throw new UnauthorizedHttpException('You cannot lock your own account.');
        } elseif ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
            throw new UnauthorizedHttpException('You cannot lock root account.');
        } else {
            if ($data == 'block') {
                $user->setEnabled(0);
            }
            else if ($data == 'unblock') {
                $user->setEnabled(1);
            }
            else {
                $user->setEnabled(!$user->isEnabled());
            }
            

            $em = $this->entityManager;
            $em->persist($user);
            $em->flush();
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $user->getName() . "'s account has been locked.");

        return $this->redirectToRoute('users');
    }

    
	#[Delete('/api/users/{id<\d+>}', name: 'api_delete_user')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/users/{id<\d+>}', name: 'delete_user', methods: ['GET', 'DELETE'])]
    public function deleteAction(Request $request, $id)
    {
        $user = $this->userRepository->find($id);
        try {
            if (!$user) {
                $this->addFlash('danger', "This user does not exist.");
                throw new NotFoundHttpException('This user does not exist.');
            } elseif ($user == $this->getUser()) {
                $this->addFlash('danger', "You cannot delete your own account.");
                throw new UnauthorizedHttpException('You cannot delete your own account.');
            } elseif ($user->hasRole('ROLE_SUPER_ADMINISTRATOR')) {
                $this->addFlash('danger', "You cannot delete root account.");
                throw new UnauthorizedHttpException('You cannot delete root account.');
            } elseif ($user->getInstances()->count() > 0) {
                $this->addFlash('danger', "You cannot delete an user who still has instances. Please stop them and try again.");
                throw new UnauthorizedHttpException('You cannot delete an user who still has instances. Please stop them and try again.');
            }
            
            else {
                $em = $this->entityManager;
                if ($user->getGroups()->count() > 0) {
                    foreach ($user->getGroups() as $group)
                        $group->getGroup()->removeUser($user);
                }
                $labs=$user->getCreatedLabs();
                if ($labs->count() > 0) {
                    foreach ($labs as $lab) {
                        $this->logger->debug("Modify lab's author: ".$lab->getName());
                        $this->addFlash('success', "The author of laboratory ".$lab->getName()." is modified to ".$this->getUser()->getName());
                        $lab->setAuthor($this->getUser());
                    }
                }
                $devices = $user->getCreatedDevices();
                if ($devices->count() > 0) {
                    foreach ($devices as $device) {
                        $this->logger->debug("Modify device's author: ".$device->getName());
                        $this->addFlash('success', "The author of device ".$device->getName()." is modified to ".$this->getUser()->getName());
                        $device->setAuthor($this->getUser());
                    }
                }
                $this->logger->info("User ".$user->getName()." is deleted by user ".$this->getUser()->getName());
                $em->remove($user);
                $em->flush();
                $this->addFlash('success', $user->getName() . "'s account has been deleted.");
            }
        }
        catch (Exception $e) {
            $this->logger->error("deleteAction Exception: ".$e);
            return $this->redirectToRoute('users');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('users');
    }

    /**
     * The CSV must be in the following format :
     * Name, firstname,email,password
     * @return array The number of elements added
     */
    public function createUserFromCSV($filehandler,$file)
    {
        $row = 0;
        $line = array();
        $addedUsers = array();
        $validator = Validation::createValidator();
        $entityManager = $this->entityManager;

        $error=false;

        if (($data = fgetcsv($filehandler, 1000, ",")) !== FALSE) {
            if ( in_array("firstname",array_map('strtolower',$data)) && in_array("lastname",array_map('strtolower',$data)) 
            && in_array("email",array_map('strtolower',$data)) ) {
                //$this->logger->debug("Find first line in CSV file");

                $csv = array_map('str_getcsv', file($file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES));
                array_walk($csv, function(&$a) use ($csv) {
                    $a = array_combine($csv[0], $a);
                });
                $this->logger->debug("firstname in CSV file :",$csv);
            }
            else {
                $this->addFlash('danger',"File format is incorrect");
                $error=true;
            }
        }
    
        if (!$error) {
            $row=0;
            foreach ($csv as $line_num => $line) {
                if ($line_num > 0) {
                    if (count($line) > 3) {
                        $lastName = $line['lastname'];
                        $firstName = $line['firstname'];
                        $group = $line['group'];
                        $email = $line['email'];
                        $user=$this->userRepository->findOneByEmail($email);
                        if ($user==null) {
                            $password = $this->generateStrongPassword(); // trim newline because this is the last field
                            $user = new User();
                            $user
                                ->setLastName($lastName)
                                ->setFirstName($firstName)
                                ->setEmail($email)
                                ->setPassword($this->passwordHasher->hashPassword($user, $password));

                            $this->logger->info("User importation by ".$this->getUser()->getName().": ".$firstName." ".$lastName." ".$email);

                            $validEmail = count($validator->validate($email, [new ConstraintsEmail()])) === 0;

                            if ($validEmail && $this->userRepository->findByEmail($email) == null) {
                                var_dump($user->getName());
                                $entityManager->persist($user);
                                $this->sendNewAccountEmail($user, $password);
                                $addedUsers[$row] = $user;

                                if ($group != "") {
                                    if ( !$group_wanted=$this->groupRepository->findOneByName($group) ) {
                                        $this->logger->info("Creation of ".$group." group by ".$this->getUser()->getName());
                                        $group_wanted = new Group();
                                        $group_wanted->setName($group);
                                        $group_wanted->setVisibility(Group::VISIBILITY_PRIVATE);
                                        $slug_wanted=str_replace(" ","-",$group);
        
                                        $slug_list=$this->groupRepository->findOneBySlug($slug_wanted);
                                        $i=1;
                                        while($slug_list) {
                                            if ($slug_wanted==$slug_list->getSlug()) {
                                                $this->logger->debug("The slug ".$slug_wanted." exists");
                                                $slug_wanted=$slug_wanted.$i;
                                                $i++;
                                            }
                                            $slug_list=$this->groupRepository->findOneBySlug($slug_wanted);
                                        }
                                        $this->logger->debug("Creation of ".$group." with slug ".$slug_wanted);
                                        $group_wanted->setSlug($slug_wanted);
                                        $entityManager->persist($group_wanted);
                                        $group_wanted->addUser($this->getUser(), Group::ROLE_OWNER);
                                    }
                                    if (!$user->isMemberOf($group_wanted))
                                        $group_wanted->addUser($user);
                                }
                            }

                        }
                        
                        $row++;
                    }
                    $entityManager->flush();
                }
            }
    
        $entityManager->flush();
        return $addedUsers;
        }
    }

    /**
     * Send an email to a user with his password.
     *
     * @param User $user
     * @return void
     */
    private function sendNewAccountEmail($user, $password)
    {
        $message = (new Email())
            ->from($this->contact_mail)
            ->to($user->getEmail())
            ->subject('Your RemoteLabz account')
            ->html(
                $this->renderView(
                    'emails/new_user.html.twig',
                    [
                        'name' => $user->getName(),
                        'link' => $this->generateUrl('login', [],  UrlGeneratorInterface::ABSOLUTE_URL),
                        'password' => $password
                    ]
                )
            );
            try {
                $this->mailer->send($message);
                $this->logger->info("Mail send to ".$user->getEmail()." by ".$this->getUser()->getName());
            } catch(TransportExceptionInterface $e) {
                $this->logger->error("Send mail problem :". $e->getMessage());
            }
    }

    #[Route(path: '/profile', name: 'user_profile')]
    public function profileAction(Request $request)
    {
        $user = $this->getUser();
        $userForm = $this->createForm(UserProfileType::class, $user);
        $passwordForm = $this->createForm(UserPasswordType::class, new User());
        $userForm->handleRequest($request);
        $passwordForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $user = $userForm->getData();

            $entityManager = $this->entityManager;
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated.');
        }

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $password = $passwordForm->get('password')->getData();
            $newPassword = $passwordForm->get('newPassword')->getData();
            $confirmPassword = $passwordForm->get('confirmPassword')->getData();

            if ($this->passwordHasher->isPasswordValid($user, $password)) {
                if ($newPassword == $confirmPassword) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

                    $entityManager = $this->entityManager;
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

    #[Route(path: '/profile/picture', name: 'post_user_profile_picture', methods: 'POST')]
    public function profilePictureAction(Request $request, ProfilePictureFileUploader $fileUploader)
    {
        $user = $this->getUser();

        $pictureFile = $request->files->get('picture');
        if ($pictureFile) {
            $pictureFileName = $fileUploader->upload($pictureFile);
            $user->setProfilePictureFilename($pictureFileName);
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('user_profile');
    }

    #[Route(path: '/profile/picture', name: 'get_current_user_profile_picture', methods: 'GET')]
    public function getProfilePictureAction(Request $request, KernelInterface $kernel)
    {
        $user = $this->getUser();
        $size = $request->query->get('size', 96);

        $profilePicture = $user->getProfilePicture();
        $response="";

        if ($profilePicture && is_file($profilePicture)) {
            $image = file_get_contents($profilePicture);
            $image = imagecreatefrompng($profilePicture);
            $image = imagescale($image, $size, $size, IMG_GAUSSIAN);
            $imageTmp = $kernel->getCacheDir() . "/" . new Uuid();
            $image = imagepng($image, $imageTmp, 9);
            $this->logger->debug("function getProfilePictureAction profilePicture ok");
            $response = new Response(file_get_contents($imageTmp), 200);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="' . $user->getProfilePictureFilename() . '"');
        } else {
            //$url=Gravatar::getGravatar($user->getEmail(), $size);
            $picture=""; $file="";
            //$this->logger->debug("no picture for: ".$user->getName());

            /* if ($this->checkInternet()){
                try {
                    $picture = file_get_contents($url,0,stream_context_create( ["http"=> ["timeout" => '1.0']] ));
                }
                catch (Exception $e){
                    $this->logger->error("No access to gravatar: ".$e->getMessage());
                }
            }
            else
            { */
                $fileName = $this->getParameter('image_default_profile');
                $file=$this->getParameter('directory.public.images').'/'.$fileName;
                $picture = file_get_contents($file);
            //}
            $response=new Response($picture, 200, ['Content-Type' => 'image/jpeg']);
        }
        //$this->logger->debug("No profile picture saved in user profile");
        return $response;
    }

    /**
     * This function is called by the page admin/users
     */
    #[Route(path: '/users/{id<\d+>}/picture', name: 'get_user_profile_picture', methods: 'GET')]
    public function getUserProfilePictureAction(
        Request $request,
        int $id,
        KernelInterface $kernel
        )
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

        $picture="";
       /* $url=Gravatar::getGravatar($user->getEmail(), $size);
        try {
            $picture = file_get_contents($url);
            }
        catch (Exception $e){
            $this->logger->error("getUserProfilePictureAction: Impossible to connect to ".$url);
            $this->logger->error($e->getMessage());
            */
            $fileName = $this->getParameter('image_default_profile');
            $file=$this->getParameter('directory.public.images').'/'.$fileName;
            $picture=file_get_contents($file);
        //}

        return new Response($picture, 200, ['Content-Type' => 'image/jpeg']);
        }
    }

    #[Route(path: '/profile/picture', name: 'delete_user_profile_picture', methods: 'DELETE')]
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
        }/* else {

         /*   $url=Gravatar::getGravatar($user->getEmail(), $size);
            try {
                $picture = file_get_contents($url);

                return new Response($picture, 200, ['Content-Type' => 'image/jpeg']);
                }
            catch (Exception $e){
                $this->logger->error("Impossible to connect to ".$url);
                $this->logger->error($e->getMessage());
                }
        }*/
    }

    
	#[Get('/api/users/me', name: 'api_users_me')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    public function meAction()
    {
        return $this->redirectToRoute('api_get_user', ['id' => $this->getUser()->getId()]);
    }

    #[Route(path: '/profile/vpn', name: 'get_user_vpn_config', methods: 'GET')]
    public function vpnConfigurationGenerateAction(VPNConfiguratorGeneratorInterface $VPNConfigurationGenerator)
    {
        $user = $this->getUser();
        $x509 = null;
        $privateKey = null;
        $certsDir = $VPNConfigurationGenerator->getExportPath();
        $certPath = $certsDir.'/'.$user->getUserIdentifier().'.crt';
        $pkeyPath = $certsDir.'/'.$user->getUserIdentifier().'.key';
        $filesystem = new Filesystem();

        if ($filesystem->exists($certPath)) {
            if (!$this->IsCertValid($certPath)) {
                $this->logger->debug("Certificate of ".$user->getUserIdentifier()." is expired ");
                unlink($certPath);
                unlink($pkeyPath);
            }
        }

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
        $filename=$this->remotevpn_addr.'-'.$user->getUserIdentifier().'.ovpn';

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );

        $response->headers->set('Content-Type', 'application/x-openvpn-profile');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function IsCertValid($certificate_file) {
        $this->logger->debug("certificate file :".$certificate_file);

        $cert=openssl_x509_parse("file://".$certificate_file);
        $this->logger->debug("certificate information :",$cert);
        $this->logger->debug("certificate information valid from : ".date(DATE_RFC2822,$cert['validFrom_time_t'])." to ".date(DATE_RFC2822,$cert['validTo_time_t']));

        if ($cert['validFrom_time_t'] > time() || $cert['validTo_time_t']< time())
            return false;
        else
            return true;
    }

    function checkInternet()
    {
        $response="";
        $client = new Client();
            try {
                //Test if internet access
                // Test without name resolution otherwise the timeout has no effect on the name resolution. Only on the connection to the server !
                $result = $client->get($this->url_check_internet,['timeout' => 1, 'max'             => 1]);
                if ($result)
                {
                    $response=true;
                }
            }
            catch (Exception $e){
                $response=false;
                $this->logger->error("Checkinternet process - No internet access: ".$e->getMessage());
            }
        return $response;
    }

    // Generates a strong password of N length containing at least one lower case letter,
    // one uppercase letter, one digit, and one special character. The remaining characters
    // in the password are chosen at random from those four sets.
    //
    // The available characters in each set are user friendly - there are no ambiguous
    // characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
    // makes it much easier for users to manually type or speak their passwords.
    //
    // Note: the $add_dashes option will increase the length of the password by
    // floor(sqrt(N)) characters.

    private function generateStrongPassword($length = 12, $add_dashes = false, $available_sets = 'luds')
    {
        $sets = array();
        if(strpos($available_sets, 'l') !== false)
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        if(strpos($available_sets, 'u') !== false)
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        if(strpos($available_sets, 'd') !== false)
            $sets[] = '23456789';
        if(strpos($available_sets, 's') !== false)
            $sets[] = '!@#$%&*?';

        $all = '';
        $password = '';
        foreach($sets as $set)
        {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);
        for($i = 0; $i < $length - count($sets); $i++)
            $password .= $all[array_rand($all)];

        $password = str_shuffle($password);

        if(!$add_dashes)
            return $password;

        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while(strlen($password) > $dash_len)
        {
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;
        return $dash_str;
        }
    }
