<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CourseController extends AppController
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
        return $this->render('course/index.html.twig');
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
