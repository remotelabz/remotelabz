<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CourseController extends AppController
{
    /**
     * @Route("/admin/courses", name="courses")
     */
    public function indexAction(Request $request)
    {
        $course = new Course();
        $courseForm = $this->createForm(CourseType::class, $course);
        $courseForm->handleRequest($request);
        
        if ($courseForm->isSubmitted() && $courseForm->isValid()) {
            $course = $courseForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($course);
            $entityManager->flush();
            
            $this->addFlash('success', 'Course has been created.');
        }
        
        return $this->render('course/index.html.twig', [
            'courseForm' => $courseForm->createView(),
        ]);
    }
        
    /**
     * @Route("/courses", name="get_courses", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:Course');
            
        $data = $repository->findAll();
            
        return $this->json($data);
    }
        
    /**
     * @Route("/courses/{id<\d+>}", name="delete_course", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Course');
            
        $status = 200;
        $data = [];
            
        $course = $repository->find($id);
            
        if ($course == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($course);
            $em->flush();
                
            $data['message'] = 'Course has been deleted.';
        }
            
        return $this->json($data, $status);
    }
}
