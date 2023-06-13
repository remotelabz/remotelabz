<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\Entity\Group;
use App\Entity\User;
use App\Form\GroupParentType;
use App\Form\GroupType;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Repository\LabRepository;
use App\Security\ACL\GroupVoter;
use App\Service\GroupPictureFileUploader;
use App\Utils\Uuid;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Exception;
use FOS\RestBundle\Context\Context;
use FOS\RestBundle\Controller\Annotations as Rest;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        UserRepository $userRepository
    )
    {
        $this->groupRepository = $groupRepository;
        $this->logger = $logger;
        $this->labRepository = $labRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/admin/groups", name="groups")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search));

        $criteria
            ->orderBy([
                'id' => Criteria::DESC,
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

    /**
     * @Route("/groups", name="dashboard_groups")
     * @Route("/explore/groups", name="dashboard_explore_groups")
     *
     * @Rest\Get("/api/groups", name="api_groups")
     */
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
                'id' => Criteria::DESC,
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

    /**
     * @Route("/groups/new", name="new_group")
     *
     * @Rest\Post("/api/groups", name="api_new_group")
     */
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

            $entityManager = $this->getDoctrine()->getManager();
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

    /**
     * @Route("/groups/{slug}/users", name="add_user_group", methods="POST", requirements={"slug"="[\w\-\/]+"})
     */
    public function addUserAction(Request $request, string $slug, UserRepository $userRepository)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::ADD_MEMBER, $group);

        $users = $request->request->get('users');
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

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('success', sizeof($users).' users has been added to the group '.$group->getName().'.');
        }
        

        return $this->redirectToRoute('dashboard_group_members', ['slug' => $group->getPath()]);
    }

    /**
     * @Route("/groups/{slug}/user/{userId<\d+>}/delete", name="remove_user_group", methods="GET", requirements={"slug"="[\w\-\/]+"})
     */
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

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($group);
        $entityManager->flush();

        $this->addFlash('success', $user->getName().' has been removed from '.$group->getName().'.');

        return $this->redirectToRoute('dashboard_group_members', ['slug' => $slug]);
    }

    /**
     * @Rest\Put("/api/groups/{slug}/user/{id<\d+>}/role", name="update_user_role_group", requirements={"slug"="[\w\-\/]+"})
     */
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

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($group);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json(null, 200, [], ['api_get_group']);
        }

        return new Response();
    }

    /**
     * @Route("/groups/{slug}/edit", name="dashboard_edit_group", requirements={"slug"="[\w\-\/]+"})
     *
     * @Rest\Put("/api/groups/{slug}", name="api_edit_group", requirements={"slug"="[\w\-\/]+"})
     */
    public function updateAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

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

            $entityManager = $this->getDoctrine()->getManager();
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

    /**
     * @Route("/groups/{slug}/edit/parent", name="dashboard_edit_group_parent", requirements={"slug"="[\w\-\/]+"})
     */
    public function updateParentAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $parentId = $request->request->get('parent');

        if ($parentId !== null) {
            $parent = $this->groupRepository->find($parentId);
        } else {
            $parent = null;
        }


        if ($parent === null || $parent->getId() !== $group->getId()) {
            $group->setParent($parent);
            $group->setUpdatedAt(new \DateTime());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('info', 'Group namespace has been changed.');
        }

        return $this->redirectToRoute('dashboard_show_group', ['slug' => $group->getPath()]);
    }

    /**
     * @Route("/groups/{slug}/delete", name="delete_group", methods="GET", requirements={"slug"="[\w\-\/]+"})
     *
     * @Rest\Delete("/api/groups/{slug}", name="api_delete_group", requirements={"slug"="[\w\-\/]+"})
     */
    public function deleteAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::DELETE, $group);

        $entityManager = $this->getDoctrine()->getManager();
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

    /**
     * @Route("/groups/{slug}/members", name="dashboard_group_members", requirements={"slug"="[\w\-\/]+"})
     */
    public function dashboardMembersAction(string $slug, SerializerInterface $serializer)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        return $this->render('group/dashboard_members.html.twig', [
            'group' => $group,
            'props' => $serializer->serialize(
                $group,
                'json',
                SerializationContext::create()->setGroups(['api_groups'])
            ),
        ]);
    }

    /**
     * @Rest\get("/api/groups/{slug}/members/{id<\d+>}", name="dashboard_group_badges", requirements={"slug"="[\w\-\/]+"})
     */
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

    /**
     * @Route("/groups/{slug}/picture", name="get_group_picture", requirements={"slug"="[\w\-\/]+"}, methods="GET")
     */
    public function getGroupPictureAction(Request $request, string $slug, KernelInterface $kernel)
    {
        $group = $this->groupRepository->findOneBySlug($slug);
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

    /**
     * @Route("/groups/{slug}/picture", name="upload_group_picture", requirements={"slug"="[\w\-\/]+"}, methods="POST")
     */
    public function uploadGroupPictureAction(Request $request, GroupPictureFileUploader $fileUploader, string $slug)
    {
        $group = $this->groupRepository->findOneBySlug($slug);
        $fileUploader->setGroup($group);

        $pictureFile = $request->files->get('picture');
        if ($pictureFile) {
            $pictureFileName = $fileUploader->upload($pictureFile, $group);
            $group->setPictureFilename($pictureFileName);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($group);
        $entityManager->flush();

        return $this->redirectToRoute('dashboard_show_group', [
            'slug' => $slug,
        ]);
    }

    /**
     * @Route("/admin/groups/{slug}",
     *  name="admin_show_group",
     *  methods="GET",
     *  requirements={"slug"="[\w\-\/]+"}
     * )
     */
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

     /**
     * @Route("/groups/{slug}/addlab", name="add_lab_group", methods="POST",
     * requirements={"slug"="[\w\-\/]+"})
     */
    public function addLabAction(Request $request, string $slug, LabRepository $labRepository)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::ADD_MEMBER, $group);
        
        $labs = $request->request->get('labs');
        
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

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('success', sizeof($labs).' laboratory(ies) has been added to the group '.$group->getName().'.');
        }

        return $this->redirectToRoute('dashboard_add_lab_group', [
            'slug' => $slug,
        ]);
    }

     /**
     * @Route("/groups/{slug}/removelab/{id<\d+>}", name="rem_lab_group", methods="GET",
     * requirements={"slug"="[\w\-\/]+"})
     */
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

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($group);
        $entityManager->flush();

        

        return $this->redirectToRoute('dashboard_add_lab_group', [
            'slug' => $slug,
        ]);
    }

    /**
     * @Route("/groups/{slug}/group_labs", name="dashboard_add_lab_group", methods="GET",
     * requirements={"slug"="[\w\-\/]+"})
     *
     */
    public function showaddlabAction(Request $request, string $slug)
    {
        if (!$group = $this->groupRepository->findOneBySlug($slug)) {
            throw new NotFoundHttpException('Group with URL '.$slug.' does not exist.');
        }
        //$lab= Fetch all laboratories of privileged user of this group
        $labs=$group->getLabs();
        
        return $this->render('group/dashboard_add_lab.html.twig', [
            'group' => $group,
            'labs' => $labs,
            ]);
    }

    

    /**
     * @Route("/groups/{slug}",
     *  name="dashboard_show_group",
     *  methods="GET",
     *  requirements={"slug"="[\w\-\/]+"}
     * )
     *
     * @Rest\Get("/api/groups/{slug}", name="api_get_group", requirements={"slug"="[\w\-\/]+"})
     */
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
