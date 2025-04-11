<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\Entity\Group;
use App\Entity\User;
use App\Form\GroupParentType;
use App\Form\GroupType;
use App\Form\GroupInstanceType;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Repository\LabRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\InstanceRepository;
use App\Security\ACL\GroupVoter;
use App\Service\GroupPictureFileUploader;
use App\Utils\Uuid;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Exception;
use FOS\RestBundle\Context\Context;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\Security;
use Doctrine\ORM\EntityManagerInterface;

class GroupController extends Controller
{
    private $logger;
    private $labRepository;
    private $userRepository;

    /** @var GroupRepository */
    protected $groupRepository;

    public function __construct(
        GroupRepository $groupRepository,
        LoggerInterface $logger,
        LabRepository $labRepository,
        LabInstanceRepository $labInstanceRepository,
        SerializerInterface $serializer,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    )
    {
        $this->groupRepository = $groupRepository;
        $this->logger = $logger;
        $this->labRepository = $labRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->serializer = $serializer;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    #[Route(path: '/admin/groups', name: 'groups')]
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search));

        $criteria
            ->orderBy([
                'name' => Criteria::ASC,
            ])
        ;

        $groups = $this->groupRepository->matching($criteria);
        $count = $groups->count();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($groups->getValues());
        }

        return $this->render('group/index.html.twig', [
            'groups' => $groups->slice($page * $limit - $limit, $limit),
            'count' => $count,
            'search' => $search,
            'limit' => $limit,
            'page' => $page,
        ]);
    }

    
	#[Get('/api/groups', name: 'api_groups')]
	#[IsGranted("ROLE_USER", message: "Access denied.")]
    #[Route(path: '/groups', name: 'dashboard_groups')]
    #[Route(path: '/explore/groups', name: 'dashboard_explore_groups')]
    public function dashboardIndexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);
        $rootOnly = (bool) $request->query->get('root_only', false);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search));

        if ($rootOnly) {
            $criteria->andWhere(Criteria::expr()->isNull('parent'));
        }

        $criteria
            ->orderBy([
                'name' => Criteria::ASC
            ]);

        /** @param Group $value */
        $groups = $this->groupRepository->matching($criteria);

        // filter groups if user does not have access to them
        $groups = $this->filterAccessDeniedGroups($groups);

        $matchedRoute = $request->get('_route');

        if ('dashboard_groups' == $matchedRoute) {
            /** @var User $user */
            $user = $this->getUser();
            /** @param Group $group */
            $groups = $groups->filter(function ($group) use ($user) {
                return $user->isMemberOf($group);
            });
            //$groups=$user->getGroups();

        } elseif ('dashboard_explore_groups' == $matchedRoute) {
            /** @param Group $group */
            $groups = $groups->filter(function ($group) {
                return Group::VISIBILITY_PUBLIC === $group->getVisibility();
            });
        }

        $context = $request->query->get('context');

        if ('json' === $request->getRequestFormat()) {
            /*$groups_secured=array();
            foreach($groups->getValues() as $group) {
                $groups_secured[]=[ "path" => $group->getPath(), "id" => $group->getId(), "uuid" => $group->getUuid(), "name" => $group->getName(), "children" => $group->getChildren(), "users" => $group->getUsers(), "labs" => $group->getLabs(), "owner" => $group->getOwner(), "visilibity" => $group->getVisibility()];
                //$this->logger->debug("data from json groupcontroller ".$group->getId()." ".$group->getUuid()." ".$group->getName());
            }
            

            
            $result=$this->json($groups_secured, 200, [], [$request->get('_route')]);*/
            //$this->logger->debug("data from json groupcontroller ".$request->get('_route'). " context ". $context);
            if (is_null($context))
                $result=$this->json($groups->getValues(), 200, [], [$request->get('_route')]);
            else
                $result=$this->json($groups->getValues(), 200, [], [$context]);
            return $result;
        }

        return $this->render('group/dashboard_index.html.twig', [
            'groups' => $groups->slice($page * $limit - $limit, $limit),
            'search' => $search,
            'limit' => $limit,
            'page' => $page,
        ]);
    }

    
	#[Get('/api/groups/{slug}/instances', name: 'api_group_instances', requirements: ["slug"=>"[\w\-\/]+"])]
    #[Route(path: '/group/{slug}/instances', name: 'dashboard_group_instances', requirements: ['slug' => '[\w\-\/]+'])]
    public function dashboardGroupInstancesAction(Request $request , string $slug, LabInstanceRepository $labInstanceRepository, SerializerInterface $serializer)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);
        $labs = [];
        $groupInstance = $request->query->get('group_instance');
        $filter = $groupInstance ? $groupInstance['filter'] : "allLabs";
        $page = (int)$request->query->get('page', 1);
        $limit = 10;
        
        $groupInstanceForm = $this->createForm(GroupInstanceType::class, ["action"=> "/group/".$slug."/instances", "method"=>"GET", "slug"=>$slug, "filter"=>$filter]);
        $groupInstanceForm->handleRequest($request);

        if ($filter == "allLabs") {
            $instances = $labInstanceRepository->findByGroup($group, $this->getUser());
        }
        else {
            $instances = $this->fetchGroupInstancesByLabUuid($slug, $filter);
        }
        /*foreach ($instances as $instance) {
            $exists = false;
            foreach($labs as $lab) {
                if ($instance->getLab() == $lab) {
                    $exists = true;
                }
            }
            if ($exists == false) {
                array_push($labs, $instance->getLab());
            }
        }*/
        $AllLabInstances = [];
        foreach ($instances as $instance) {
            
            array_push($AllLabInstances, $instance);
        }

        $count = count($instances);
        try {
            $AllLabInstances = array_slice($AllLabInstances, $page * $limit - $limit, $limit);
        } catch (ORMException $e) {
            throw new NotFoundHttpException('Incorrect order field or sort direction', $e, $e->getCode());
        }
        if ('json' === $request->getRequestFormat()) {
            return $this->json($AllLabInstances, 200, [], ['api_get_lab_instance']);
        }
        
        $GroupInstancesProps = [
            //'instances'=> $instances,
            //'labs'=> $labs,
            'group'=> $group,
            'user'=>$this->getUser()
        ];

        $props=$serializer->serialize(
            $GroupInstancesProps,
            'json',
            //SerializationContext::create()->setGroups(['api_get_lab', 'api_get_user', 'api_get_group', 'api_get_lab_instance', 'api_get_device_instance'])
            SerializationContext::create()->setGroups(['api_get_lab_instance','api_get_lab',])
        );

        return $this->render('group/dashboard_group_instances.html.twig', [
            'labInstances' => $AllLabInstances,
            'group' => $group,
            //'labs' => $labs,
            'props' => $props,
            'groupInstanceForm' => $groupInstanceForm->createView(),
            'count'=>$count,
            'limit'=>$limit,
            'page'=>$page
        ]);
    }

    public function fetchGroupInstancesByLabUuid(string $slug, string $uuid)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }
    
        if (!$lab = $this->labRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('Lab with UUID '.$uuid.' does not exist.');
        }
        
        if (!$group->getLabs()->contains($lab)) {
            throw new BadRequestHttpException('This lab '.$lab->getName().' in not in the group '.$slug.'.');
        }
        $instances = $this->labInstanceRepository->findByGroupAndLabUuid($group, $lab);

        if (!$instances) $instances = [];

        return $instances;

    }

    private function filterAccessDeniedGroups($groups) {
        return $groups->filter(function ($group) {
            if (count($group->getChildren()) > 0) {
                $group->setChildren($this->filterAccessDeniedGroups($group->getChildren()));
            }

            // check again to let parent in the list if there's still at least 1 child
            if (count($group->getChildren()) > 0) {
                return true;
            }

            return $this->isGranted(GroupVoter::VIEW, $group);
        });
    }

    
	#[Post('/api/groups', name: 'api_new_group')]
	#[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    #[Route(path: '/groups/new', name: 'new_group')]
    public function newAction(Request $request, ValidatorInterface $validator)
    {
        $group = new Group();
        $groupForm = $this->createForm(GroupType::class, $group);
        $groupForm->handleRequest($request);

        if ('json' === $request->getContentType()) {
            $group = json_decode($request->getContent(), true);
            $groupForm->submit($group);
        }

        $data = [
            'form' => $groupForm->createView(),
            'group' => $group,
        ];

        if ($request->query->has('parent_id')) {
            $data['parent'] = $this->groupRepository->find($request->query->get('parent_id'));
        }

        if ($groupForm->isSubmitted() && $groupForm->isValid()) {
            /** @var Group $group */
            $group = $groupForm->getData();
            if ($request->query->has('parent_id')) {
                $group->setParent($this->groupRepository->find($request->query->get('parent_id')));
            }
            $group->addUser($this->getUser(), Group::ROLE_OWNER);

            $errors = $validator->validate($group);

            if (count($errors) > 0) {
                throw new BadRequestHttpException((string) $errors);
            }

            $entityManager = $this->entityManager;
            $entityManager->persist($group);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($group, 201, [], ['api_get_group']);
            }

            $this->addFlash('success', 'Group has been created.');

            return $this->redirectToRoute('dashboard_groups');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($groupForm, 200);
        }

        return $this->render('group/new.html.twig', $data);
    }

    #[Route(path: '/groups/{slug}/users', name: 'add_user_group', methods: 'POST', requirements: ['slug' => '[\w\-\/]+'])]
    public function addUserAction(Request $request, string $slug, UserRepository $userRepository)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::ADD_MEMBER, $group);

        #$users = $request->request->get('users');
        $users = $request->request->all()['users'] ?? [];

        $role = $request->request->get('role', 'user');
        // trim empty values
        $users = array_filter(array_map('trim', $users), 'strlen');

        if (0 === sizeof($users)) {
            if ('html' === $request->getRequestFormat()) {
                $this->addFlash('warning', 'No user selected.');
            } else {
                throw new BadRequestHttpException();
            }
        } else {
            $users = $userRepository->findBy(['id' => $users]);

            foreach ($users as $user) {
                $group->addUser($user, $role);
                $this->logger->info("User ".$user->getName()." added in group ".$group->getPath()." by ".$this->getUser()->getName());
            }

            $entityManager = $this->entityManager;
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('success', sizeof($users).' users has been added to the group '.$group->getName().'.');
        }
        

        return $this->redirectToRoute('dashboard_group_members', ['slug' => $group->getPath()]);
    }

    
	#[Delete('/api/groups/{slug}/user/{userId<\d+>}', name: 'api_delete_user_group', requirements: ["slug"=>"[\w\-\/]+"])]
    #[Route(path: '/groups/{slug}/user/{userId<\d+>}/delete', name: 'remove_user_group', methods: 'GET', requirements: ['slug' => '[\w\-\/]+'])]
    public function removeUserAction(Request $request, string $slug, int $userId, UserRepository $userRepository)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        if (!$user = $userRepository->find($userId)) {
            throw new NotFoundHttpException('User with ID '.$userId.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        $group->removeUser($user);

        $entityManager = $this->entityManager;
        $entityManager->persist($group);
        $entityManager->flush();

        $this->addFlash('success', $user->getName().' has been removed from '.$group->getName().'.');

        return $this->redirectToRoute('dashboard_group_members', ['slug' => $slug]);
    }

    
	#[Put('/api/groups/{slug}/user/{id<\d+>}/role', name: 'update_user_role_group', requirements: ["slug"=>"[\w\-\/]+"])]
    public function updateUserRoleAction(Request $request, string $slug, int $id, UserRepository $userRepository)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        if (!$user = $userRepository->find($id)) {
            throw new NotFoundHttpException('User with ID '.$id.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        try {
            $group->setUserRole($user, $request->request->get('role'));
        } catch (Exception $e) {
            throw new BadRequestHttpException("Role must be one of 'user' or 'admin'.");
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($group);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json(null, 200, [], ['api_get_group']);
        }

        return new Response();
    }

    
	#[Put('/api/groups/{slug}', name: 'api_edit_group', requirements: ["slug"=>"[\w\-\/]+"])]
    #[Route(path: '/groups/{slug}/edit', name: 'dashboard_edit_group', requirements: ['slug' => '[\w\-\/]+'])]
    public function updateAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }
        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        $groupForm = $this->createForm(GroupType::class, $group);
        $groupForm->handleRequest($request);

        if ('json' === $request->getContentType()) {
            $group = json_decode($request->getContent(), true);
            $groupForm->submit($group, false);
        }

        if ($groupForm->isSubmitted() && $groupForm->isValid()) {
            /** @var Group $group */
            $group = $groupForm->getData();
            $group->setUpdatedAt(new \DateTime());

            $entityManager = $this->entityManager;
            $entityManager->persist($group);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($group, 200, [], ['api_get_group']);
            }

            $this->addFlash('info', 'Group has been edited.');

            return $this->redirectToRoute('dashboard_show_group', ['slug' => $group->getPath()]);
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($groupForm);
        }

        return $this->render('group/dashboard_settings.html.twig', [
            'form' => $groupForm->createView(),
            'group' => $group,
        ]);
    }

    #[Route(path: '/groups/{slug}/edit/parent', name: 'dashboard_edit_group_parent', requirements: ['slug' => '[\w\-\/]+'])]
    public function updateParentAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);
        #$parentId = $request->request->get('parent');
        $parentId = $request->request->all()['parent'] ?? [];

        if ($parentId !== null) {
            $parent = $this->groupRepository->find($parentId);
        } else {
            $parent = null;
        }

        if ($parent === null || $parent->getId() !== $group->getId()) {
            $group->setParent($parent);
            $group->setUpdatedAt(new \DateTime());

            $entityManager = $this->entityManager;
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('info', 'Group namespace has been changed.');
        }

        return $this->redirectToRoute('dashboard_show_group', ['slug' => $group->getPath()]);
    }

    
	#[Delete('/api/groups/{slug}', name: 'api_delete_group', requirements: ["slug"=>"[\w\-\/]+"])]
    #[Route(path: '/groups/{slug}/delete', name: 'delete_group', methods: 'GET', requirements: ['slug' => '[\w\-\/]+'])]
    public function deleteAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::DELETE, $group);

        $entityManager = $this->entityManager;
        $entityManager->remove($group);

        try {
            $entityManager->flush();

            $this->addFlash('success', $group->getName().' has been deleted.');
        } catch (ForeignKeyConstraintViolationException $e) {
            $message = 'Instances of this group are still running. Please delete them first before deleting this group.';

            $this->addFlash('warning', $message);

            if ('json' === $request->getRequestFormat()) {
                return $this->json($message, 400);
            }

            return $this->redirectToRoute('dashboard_edit_group', ['slug' => $slug]);
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('dashboard_groups');
    }

    
	#[Get('/api/groups/{slug}/members', name: 'app_group_members', requirements: ["slug"=>"[\w\-\/]+"])]
    #[Route(path: '/groups/{slug}/members', name: 'dashboard_group_members', requirements: ['slug' => '[\w\-\/]+'])]
    public function dashboardMembersAction(string $slug, SerializerInterface $serializer, Request $request)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $addGroupUserFromFileForm = $this->createFormBuilder([])
            ->add('file', FileType::class, [
                "help" => "Accepted formats: csv",
                "attr" => [
                    "accepted" => ".csv",
                ]
            ])
            ->add('submit', SubmitType::class)
            ->getForm();

        $addGroupUserFromFileForm->handleRequest($request);

        if ($addGroupUserFromFileForm->isSubmitted() && $addGroupUserFromFileForm->isValid()) {
            $file = $addGroupUserFromFileForm->getData()['file'];

            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (in_array($fileExtension, ['csv'])) {
                $fileSocket = fopen($file, 'r');

                $addedUsers = [];

                switch ($fileExtension) {
                    case 'csv':
                        $addedUsers = $this->importUserFromCSV($fileSocket,$file,$group);
                        break;
                }
                if ($addedUsers && count($addedUsers) > 0) {
                    $this->addFlash('success', 'Les utilisateurs ont été ajoutés au groupe');
                } else {
                    $this->addFlash(
                        'warning',
                        'Aucun utilisateur créé. Veuillez vérifier que les
                        utilisateurs spécifiés dans le fichier existent ou que le format du fichier est correct.'
                    );
                }

                fclose($fileSocket);
            } else {
                $this->addFlash('danger', "Ce type de fichier n'est pas accepté.");
            }
            return $this->redirectToRoute('dashboard_group_members', [
                'slug'=>$slug,
            ]);

        }
        if ('json' === $request->getRequestFormat()) {
            return $this->json($group, 200, [], ['api_groups']);
        }

        return $this->render('group/dashboard_members.html.twig', [
            'group' => $group,
            'props' => $serializer->serialize(
                $group,
                'json',
                SerializationContext::create()->setGroups(['api_groups'])
            ),
            'addGroupUserFromFileForm' => $addGroupUserFromFileForm->createView(),
        ]);
    }

    /**
     * The CSV must be in the following format :
     * email
     * @return array The number of elements added
     */
    public function importUserFromCSV($filehandler,$file,$group)
    {
        $row = 0;
        $line = array();
        $addedUsers = array();
        $entityManager = $this->entityManager;

        $error=false;

        if (($data = fgetcsv($filehandler, 1000, ",")) !== FALSE) {
            if ( in_array("email",array_map('strtolower',$data)) ) {
                //$this->logger->debug("Find first line in CSV file");

                $csv = array_map('str_getcsv', file($file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES));
                array_walk($csv, function(&$a) use ($csv) {
                    $a = array_combine($csv[0], $a);
                });
                $this->logger->debug("email in CSV file :",$csv);
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
                    if (count($line) >= 1) {
                        $email = $line['email'];
                        $user=$this->userRepository->findOneByEmail($email);
                        if ($user != null) {
                            if (!$group->hasUser($user)) {
                                $group->addUser($user);
                                $addedUsers[$row] = $user;
                                $this->logger->info($this->getUser()->getName()."imported ".$user->getEmail()."in the group ".$group->getName());
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
    
    #[Get('/api/groups/{slug}/members/{id<\d+>}', name: 'dashboard_group_badges', requirements: ["slug"=>"[\w\-\/]+"])]
    public function updateRoleDisplay(string $slug, SerializerInterface $serializer, int $id)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }
        $user = $this->userRepository->find($id);
        $html = '<span class="fw600">'. $user->getName() . '</span>';
        if($group->isOwner($user)) {
            $html .= '<label class="badge badge-info ml-2 mb-0">Owner</label>';
        }
        if($group->isAdmin($user)) {
            $html .= '<label class="badge badge-warning ml-2 mb-0">Admin</label>';
        }
        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=> 'success',
            'data' => [
                'html' => $html,
                'user' => $user->getId()]
           ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    #[Route(path: '/groups/{slug}/picture', name: 'get_group_picture', requirements: ['slug' => '[\w\-\/]+'], methods: 'GET')]
    public function getGroupPictureAction(Request $request, string $slug, KernelInterface $kernel)
    {
        $group = $this->groupRepository->findOneBySlug($slug);
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        $size = $request->query->get('size', 128);
        $picture = $group->getPicture();

        if ($picture && is_file($picture)) {
            $image = file_get_contents($picture);
            $image = imagecreatefrompng($picture);
            $image = imagescale($image, $size, $size, IMG_GAUSSIAN);
            $imageTmp = $kernel->getCacheDir().'/'.new Uuid();
            $image = imagepng($image, $imageTmp, 9);

            return new Response(file_get_contents($imageTmp), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="'.$group->getPictureFilename().'"',
            ]);
        } else {
            return new Response(null);
        }
    }

    #[Route(path: '/groups/{slug}/picture', name: 'upload_group_picture', requirements: ['slug' => '[\w\-\/]+'], methods: 'POST')]
    public function uploadGroupPictureAction(Request $request, GroupPictureFileUploader $fileUploader, string $slug)
    {
        $group = $this->groupRepository->findOneBySlug($slug);
        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        $fileUploader->setGroup($group);

        $pictureFile = $request->files->get('picture');
        if ($pictureFile) {
            $pictureFileName = $fileUploader->upload($pictureFile, $group);
            $group->setPictureFilename($pictureFileName);
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($group);
        $entityManager->flush();

        return $this->redirectToRoute('dashboard_show_group', [
            'slug' => $slug,
        ]);
    }

    #[Route(path: '/admin/groups/{slug}', name: 'admin_show_group', methods: 'GET', requirements: ['slug' => '[\w\-\/]+'])]
    public function showAction(string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        return $this->render('group/view.html.twig', [
            'group' => $group,
        ]);
    }

     #[Route(path: '/groups/{slug}/addlab', name: 'add_lab_group', methods: 'POST', requirements: ['slug' => '[\w\-\/]+'])]
    public function addLabAction(Request $request, string $slug, LabRepository $labRepository)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::ADD_MEMBER, $group);
        
        $labs = $request->request->all()['labs'] ?? [];
        
        $labs = array_filter(array_map('trim', $labs), 'strlen');

        if (0 === sizeof($labs)) {
            if ('html' === $request->getRequestFormat()) {
                $this->addFlash('warning', 'No laboratory selected.');
            } else {
                throw new BadRequestHttpException();
            }
        } else {
            $labs = $labRepository->findBy(['id' => $labs]);

            foreach ($labs as $lab) {
                $group->addLab($lab);
                $this->logger->info("Laboratory ".$lab->getName()." added in group ".$group->getPath()." by ".$this->getUser()->getName());
            }

            $entityManager = $this->entityManager;
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('success', sizeof($labs).' laboratory(ies) has been added to the group '.$group->getName().'.');
        }

        return $this->redirectToRoute('dashboard_add_lab_group', [
            'slug' => $slug,
        ]);
    }

     #[Route(path: '/groups/{slug}/removelab/{id<\d+>}', name: 'rem_lab_group', methods: 'GET', requirements: ['slug' => '[\w\-\/]+'])]
    public function removeLabAction(Request $request, string $slug, int $id, LabRepository $labRepository)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::ADD_MEMBER, $group);
            
        $lab = $labRepository->findBy(['id' => $id]);

        $group->removeLab($lab[0]);
        $this->logger->info("Laboratory ".$lab[0]->getName()." remove from group ".$group->getPath()." by ".$this->getUser()->getName());

        $this->addFlash('success', 'Laboratory '.$lab[0]->getName().' has been added to the group '.$group->getName().'.');

        $entityManager = $this->entityManager;
        $entityManager->persist($group);
        $entityManager->flush();

        

        return $this->redirectToRoute('dashboard_add_lab_group', [
            'slug' => $slug,
        ]);
    }

    #[Route(path: '/groups/{slug}/group_labs', name: 'dashboard_add_lab_group', methods: 'GET', requirements: ['slug' => '[\w\-\/]+'])]
    public function showaddlabAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }
        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);
        //$lab= Fetch all laboratories of privileged user of this group
        $labs=$group->getLabs();
        
        return $this->render('group/dashboard_add_lab.html.twig', [
            'group' => $group,
            'labs' => $labs,
            ]);
    }

    

    
	#[Get('/api/groups/{slug}', name: 'api_get_group', requirements: ["slug"=>"[\w\-\/]+"])]
    #[Route(path: '/groups/{slug}', name: 'dashboard_show_group', methods: 'GET', requirements: ['slug' => '[\w\-\/]+'])]
    public function showDashboardAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        $context = $request->query->get('context');

        if ('json' === $request->getRequestFormat()) {
            return $this->json($group, 200, [], [$request->get('_route')]);
        }

        return $this->render('group/dashboard_view.html.twig', [
            'group' => $group,
        ]);
    }

    
}
