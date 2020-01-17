<?php

namespace App\Controller;

use Exception;
use App\Entity\User;
use App\Entity\Group;
use App\Form\GroupType;
use App\Entity\UserGroup;
use Swagger\Annotations as SWG;
use App\Security\ACL\GroupVoter;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use FOS\RestBundle\Context\Context;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class GroupController extends AbstractFOSRestController
{
    /** @var GroupRepository $groupRepository */
    protected $groupRepository;

    public function __construct(GroupRepository $groupRepository) {
        $this->groupRepository = $groupRepository;
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
                'id' => Criteria::DESC
            ])
            // ->setMaxResults($limit)
            // ->setFirstResult($page * $limit - $limit)
        ;

        $groups = $this->groupRepository->matching($criteria);
        $count = $groups->count();

        // $context = new Context();
        // $context
        //     ->addGroup("lab")
        // ;

        $view = $this->view($groups->getValues())
            ->setTemplate("group/index.html.twig")
            ->setTemplateData([
                'groups' => $groups->slice($page * $limit - $limit, $limit),
                'count' => $count,
                'search' => $search,
                'limit' => $limit,
                'page' => $page,
            ])
            // ->setContext($context)
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/groups", name="dashboard_groups")
     * @Route("/explore/groups", name="dashboard_explore_groups")
     */
    public function dashboardIndexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);
        
        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
        ;

        $criteria
            ->orderBy([
                'id' => Criteria::DESC
            ])
        ;

        /** @param Group $value */
        $groups = $this->groupRepository->matching($criteria);
        
        $matchedRoute = $request->get('_route');

        if ($matchedRoute == 'dashboard_groups') {
            /** @param Group $value */
            $groups = $groups->filter(function ($value) {
                return $value->getUsers()->contains($this->getUser());
            });
        } else if ($matchedRoute == 'dashboard_explore_groups') {
            /** @param Group $value */
            $groups = $groups->filter(function ($value) {
                return $value->getVisibility() === Group::VISIBILITY_PUBLIC;
            });
        }

        $view = $this->view($groups->getValues())
            ->setTemplate("group/dashboard_index.html.twig")
            ->setTemplateData([
                'groups' => $groups->slice($page * $limit - $limit, $limit),
                'search' => $search,
                'limit' => $limit,
                'page' => $page,
            ])
            // ->setContext($context)
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/groups/new", name="new_group")
     * 
     * @Rest\Post("/api/groups", name="api_new_group")
     * 
     * @SWG\Parameter(
     *     name="group",
     *     in="body",
     *     @SWG\Schema(ref=@Model(type=Group::class, groups={"api"})),
     *     description="Group data."
     * )
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Returns the newly created group.",
     *     @SWG\Schema(ref=@Model(type=Group::class))
     * )
     * 
     * @SWG\Tag(name="Group")
     */
    public function newAction(Request $request)
    {
        $group = new Group();
        $groupForm = $this->createForm(GroupType::class, $group);
        $groupForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $group = json_decode($request->getContent(), true);
            $groupForm->submit($group);
        } 

        $view = $this->view($groupForm)
            ->setTemplate("group/new.html.twig")
            ->setTemplateData([
                "form" => $groupForm->createView(),
                "group" => $group
            ])
        ;

        if ($groupForm->isSubmitted() && $groupForm->isValid()) {
            /** @var Group $group */
            $group = $groupForm->getData();
            $group->addUser($this->getUser(), Group::ROLE_OWNER);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('success', 'Group has been created.');

            $view->setLocation($this->generateUrl('groups'));
            $view->setStatusCode(201);
            $view->setData($group);
            // $context = new Context();
            // $context
            //     ->addGroup("group")
            // ;
            // $view->setContext($context);
        }

        return $this->handleView($view);
    }

    /**
     * @Rest\Put("/api/groups/{slug}/user/{id<\d+>}/role", name="update_user_role_group", requirements={"slug"="[\w\-\/]+"})
     * @Entity("group", expr="repository.findOneBySlug(slug)")
     */
    public function updateUserRoleAction(Request $request, Group $group, int $id, UserRepository $userRepository)
    {
        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        $user = $userRepository->find($id);

        try {
            $group->setUserRole($userRepository->find($id), $request->request->get('role'));
        } catch (Exception $e) {
            throw new BadRequestHttpException("Role must be one of 'user' or 'admin'.");
        }

        //     foreach ($users as $user) {
        //         $group->addUser($user);
        //     }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($group);
        $entityManager->flush();

        //     $this->addFlash('success', sizeof($users) . ' users has been added to the group ' . $group->getName() . '.');
        // }

        $view = $this->view(null, 200)
            // ->setTemplate('group/dashboard_members.html.twig')
            // ->setTemplateData([
            //     'group' => $group,
            // ])
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/groups/{slug}/user/{userId<\d+>}/delete", name="remove_user_group", methods="GET", requirements={"slug"="[\w\-\/]+"})
     */
    public function removeUserAction(Request $request, string $slug, int $userId, UserRepository $userRepository)
    {
        $group = $this->groupRepository->findOneBySlug($slug);
        $user = $userRepository->find($userId);

        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        //$entry = $group->getUserGroupEntry($user);
        $group->removeUser($user);

        $entityManager = $this->getDoctrine()->getManager();
        //$entityManager->remove($entry);
        $entityManager->persist($group);
        $entityManager->flush();

        $view = $this->view();

        // if request match browser route
        if ($request->attributes->get('_route') === "remove_user_group") {
            $view->setLocation($this->generateUrl('dashboard_group_members', [
                'slug' => $slug
            ]));
            $this->addFlash('success', $user->getName() . ' has been removed from ' . $group->getName() . '.');
        }

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/groups/{slug}/edit", name="edit_group", requirements={"slug"="[\w\-\/]+"})
     * 
     * @Rest\Put("/api/groups/{slug}", name="api_edit_group", requirements={"slug"="[\w\-\/]+"})
     * 
     * @SWG\Parameter(
     *     name="group",
     *     in="body",
     *     @SWG\Schema(ref=@Model(type=Group::class, groups={"api"})),
     *     description="Group data."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns the newly edited group.",
     *     @SWG\Schema(ref=@Model(type=Group::class))
     * )
     * 
     * @SWG\Tag(name="Group")
     */
    public function updateAction(Request $request, string $slug)
    {
        $group = $this->groupRepository->findOneBySlug($slug);

        if (null === $group) {
            throw new NotFoundHttpException("Group with URL " . $slug . " does not exist.");
        }

        $groupForm = $this->createForm(GroupType::class, $group);
        $groupForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $group = json_decode($request->getContent(), true);
            $groupForm->submit($group, false);
        } 

        $view = $this->view($groupForm)
            ->setTemplate("group/new.html.twig")
            ->setTemplateData([
                "form" => $groupForm->createView(),
                "group" => $group
            ])
        ;

        if ($groupForm->isSubmitted() && $groupForm->isValid()) {
            /** @var Group $group */
            $group = $groupForm->getData();
            $group->setUpdatedAt(new \DateTime());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($group);
            $entityManager->flush();

            if ($request->getRequestFormat() === 'html') {
                $this->addFlash('info', 'Group has been edited.');
                $view->setLocation($this->generateUrl('admin_show_group', ['slug' => $group->getSlug()]));
            }

            $view->setStatusCode(200);
            $view->setData($this->groupRepository->find($group->getId()));
            // $context = new Context();
            // $context
            //     ->addGroup("group")
            // ;
            // $view->setContext($context);
        }

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/groups/{slug}/delete", name="delete_group", methods="GET", requirements={"slug"="[\w\-\/]+"})
     * 
     * @Rest\Delete("/api/groups/{slug}", name="api_delete_group", requirements={"slug"="[\w\-\/]+"})
     */
    public function deleteAction(Request $request, string $slug)
    {
        $group = $this->groupRepository->findOneBySlug($slug);

        if (null === $group) {
            throw new NotFoundHttpException("Group " . $slug . " does not exist.");
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($group);
        $entityManager->flush();

        if ($request->getRequestFormat() === 'html') {
            $this->addFlash('success', $group->getName() . ' has been deleted.');
        }
        
        $view = $this->view()
            ->setLocation($this->generateUrl('groups'));
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/groups/{slug}/users", name="add_user_group", methods="POST", requirements={"slug"="[\w\-\/]+"})
     * @Entity("group", expr="repository.findOneBySlug(slug)")
     */
    public function addUserAction(Request $request, Group $group, UserRepository $userRepository)
    {
        $this->denyAccessUnlessGranted(GroupVoter::ADD_MEMBER, $group);

        $users = $request->request->get('users');
        $role = $request->request->get('role', 'user');
        // trim empty values
        $users = array_filter(array_map('trim', $users), 'strlen');

        if (sizeof($users) === 0) {
            if ($request->getRequestFormat() === 'html') {
                echo 'no user';
                $this->addFlash('warning', 'No user selected.');
            } else {
                throw new BadRequestHttpException();
            }
        } else {
            $users = $userRepository->findBy(['id' => $users]);

            foreach ($users as $user) {
                $group->addUser($user, $role);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($group);
            $entityManager->flush();

            $this->addFlash('success', sizeof($users) . ' users has been added to the group ' . $group->getName() . '.');
        }

        $view = $this->view()
            ->setLocation($this->generateUrl('dashboard_group_members', ["slug" => $group->getPath()]));
        ;

        return $this->handleView($view);
    }

    

    /**
     * @Route("/groups/{slug}/members", name="dashboard_group_members", requirements={"slug"="[\w\-\/]+"})
     */
    public function dashboardMembersAction(string $slug)
    {
        $group = $this->groupRepository->findOneBySlug($slug);

        if (!$group) {
            throw new NotFoundHttpException("Group with URL " . $slug . " does not exist.");
        }

        $view = $this->view($group, 200)
            ->setTemplate("group/dashboard_members.html.twig")
            ->setTemplateData([
                'group' => $group,
            ])
            // ->setContext($context)
        ;
 
        return $this->handleView($view);
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
       $group = $this->groupRepository->findOneBySlug($slug);

       if (!$group) {
           throw new NotFoundHttpException("Group with URL " . $slug . " does not exist.");
       }

       // $context = new Context();
       // $context->setGroups([
       //     "primary_key",
       //     "group",
       //     "author" => [
       //         "primary_key"
       //     ],
       //     "editor"
       // ]);

       $view = $this->view($group, 200)
           ->setTemplate("group/view.html.twig")
           ->setTemplateData([
               'group' => $group,
           ])
           // ->setContext($context)
       ;

       return $this->handleView($view);
   }

   /**
     * @Route("/groups/{slug}",
     *  name="dashboard_show_group",
     *  methods="GET",
     *  requirements={"slug"="[\w\-\/]+"}
     * )
     * 
     * @Rest\Get("/api/groups/{slug}", name="api_get_group", requirements={"slug"="[\w\-\/]+"})
     * 
     * @SWG\Parameter(
     *     name="slug",
     *     in="path",
     *     type="string",
     *     description="URL of the group."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns requested group",
     *     @Model(type=Group::class)
     * )
     * 
     * @SWG\Tag(name="Group")
     */
    public function showDashboardAction(string $slug)
    {
        $group = $this->groupRepository->findOneBySlug($slug);
 
        if (!$group) {
            throw new NotFoundHttpException("Group with URL " . $slug . " does not exist.");
        }
 
        // $context = new Context();
        // $context->setGroups([
        //     "primary_key",
        //     "group",
        //     "author" => [
        //         "primary_key"
        //     ],
        //     "editor"
        // ]);
 
        $view = $this->view($group, 200)
            ->setTemplate("group/dashboard_view.html.twig")
            ->setTemplateData([
                'group' => $group,
            ])
            // ->setContext($context)
        ;
 
        return $this->handleView($view);
    }
}
