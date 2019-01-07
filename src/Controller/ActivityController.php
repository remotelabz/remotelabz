<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Form\ActivityType;
use App\Utils\RequestType;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ActivityController extends AppController
{
    /**
     * @Route("/admin/activities", name="activities")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByNameLike($search);
        } else {
            $data = $repository->findAll();
        }

        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data);
        }
        
        return $this->render('activity/index.html.twig', [
            'activities' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/activities/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_activity",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data);
        }
        
        return $this->render('activity/view.html.twig', [
            'activity' => $data
        ]);
    }

    /**
     * @Route("/admin/activities/new", name="new_activity")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $activity = new Activity();
        $activityForm = $this->createForm(ActivityType::class, $activity);
        $activityForm->handleRequest($request);
        
        if ($activityForm->isSubmitted() && $activityForm->isValid()) {
            $activity = $activityForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($activity);
            $entityManager->flush();
            
            $this->addFlash('success', 'Activity has been created.');

            return $this->redirectToRoute('activities');
        }
        
        return $this->render('activity/new.html.twig', [
            'activityForm' => $activityForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/activities/{id<\d+>}/edit", name="edit_activity", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');

        $activity = $repository->find($id);

        if (null === $activity) {
            throw new NotFoundHttpException();
        }

        $activityForm = $this->createForm(ActivityType::class, $activity);
        $activityForm->handleRequest($request);
        
        if ($activityForm->isSubmitted() && $activityForm->isValid()) {
            $activity = $activityForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($activity);
            $entityManager->flush();
            
            $this->addFlash('success', 'Activity has been edited.');

            return $this->redirectToRoute('show_activity', [
                'id' => $id
            ]);
        }
        
        return $this->render('activity/new.html.twig', [
            'activityForm' => $activityForm->createView(),
            'id' => $id,
            'name' => $activity->getName()
        ]);
    }
        
    /**
     * @Route("/admin/activities/{id<\d+>}", name="delete_activity", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');
            
        $data = null;
        $status = 200;
            
        $activity = $repository->find($id);
            
        if ($activity == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($activity);
            $em->flush();
                
            $data = [
                'message' => 'Activity has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('activities');
    }
}
