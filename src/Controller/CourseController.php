<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use FOS\RestBundle\Context\Context;
use App\Repository\CourseRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;

class CourseController extends AbstractFOSRestController
{
    public $courseRepository;

    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }
    /**
     * @Route("/admin/courses", name="courses")
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

        $courses = $this->courseRepository->matching($criteria);
        $count = $courses->count();

        // $context = new Context();
        // $context
        //     ->addGroup("lab")
        // ;

        $view = $this->view($courses->getValues())
            ->setTemplate("course/index.html.twig")
            ->setTemplateData([
                'courses' => $courses->slice($page * $limit - $limit, $limit),
                'count' => $count,
                'search' => $search,
                'limit' => $limit,
                'page' => $page,
            ])
            // ->setContext($context)
        ;

        return $this->handleView($view);
        //return $this->render('course/index.html.twig');
    }
    
    /**
     * @Route("/admin/courses/new", name="new_course", methods={"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $course = new Course();
        $courseForm = $this->createForm(CourseType::class, $course);
        $courseForm->handleRequest($request);

        if ($courseForm->isSubmitted() && $courseForm->isValid()) {
            /** @var Course $course */
            $course = $courseForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($course);
            $entityManager->flush();

            $this->addFlash('success', 'Course has been created.');

            return $this->redirectToRoute('courses');
        }

        return $this->render('course/new.html.twig', [
            'courseForm' => $courseForm->createView()
        ]);
    }

    /**
     * @Route("/admin/courses/{id<\d+>}/edit", name="edit_course", methods={"GET", "POST"})
     */
    public function editAction(Request $request, int $id)
    {
        $course = $this->courseRepository->find($id);

        if (null === $course) {
            throw new NotFoundHttpException();
        }
        
        $courseForm = $this->createForm(CourseType::class, $course);
        $courseForm->handleRequest($request);

        if ($courseForm->isSubmitted() && $courseForm->isValid()) {
            /** @var Course $course */
            $course = $courseForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($course);
            $entityManager->flush();

            $this->addFlash('success', 'Course has been edited.');

            return $this->redirectToRoute('courses');
        }

        return $this->render('course/new.html.twig', [
            'courseForm' => $courseForm->createView(),
            'course' => $course
        ]);
    }
        
    /**
     * @Route("/courses", name="get_courses", methods="GET")
     */
    public function cgetAction()
    {
        return $this->renderJson($this->courseRepository->findAll());
    }
        
    /**
     * @Route("/admin/courses/{id<\d+>}", name="delete_course", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $status = 200;
        $data = [];

        $course = $this->courseRepository->find($id);

        if ($course == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($course);
            $em->flush();

            $data['message'] = 'Course has been deleted.';
        }
            
        return $this->renderJson($data, $status);
    }
}
