<?php

namespace App\Controller;

use App\Entity\POD;
use App\Form\PODType;
use App\Utils\RequestType;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PODController extends AppController
{
    /**
     * @Route("/admin/pods", name="pods")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:POD');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByNameLike($search);
        } else {
            $data = $repository->findAll();
        }

        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data);
        }
        
        return $this->render('pod/index.html.twig', [
            'pods' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/pods/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_pod",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:POD');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data);
        }
        
        return $this->render('pod/view.html.twig', [
            'pod' => $data
        ]);
    }

    /**
     * @Route("/admin/pods/new", name="new_pod")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $pod = new POD();
        $podForm = $this->createForm(PODType::class, $pod);
        $podForm->handleRequest($request);
        
        if ($podForm->isSubmitted() && $podForm->isValid()) {
            $pod = $podForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($pod);
            $entityManager->flush();
            
            $this->addFlash('success', 'POD has been created.');

            return $this->redirectToRoute('pods');
        }
        
        return $this->render('pod/new.html.twig', [
            'podForm' => $podForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/pods/{id<\d+>}/edit", name="edit_pod", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:POD');

        $pod = $repository->find($id);

        if (null === $pod) {
            throw new NotFoundHttpException();
        }

        $podForm = $this->createForm(PODType::class, $pod);
        $podForm->handleRequest($request);
        
        if ($podForm->isSubmitted() && $podForm->isValid()) {
            $pod = $podForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($pod);
            $entityManager->flush();
            
            $this->addFlash('success', 'POD has been edited.');

            return $this->redirectToRoute('show_pod', [
                'id' => $id
            ]);
        }
        
        return $this->render('pod/new.html.twig', [
            'podForm' => $podForm->createView(),
            'id' => $id,
            'name' => $pod->getName()
        ]);
    }
        
    /**
     * @Route("/admin/pods/{id<\d+>}", name="delete_pod", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:POD');
            
        $data = null;
        $status = 200;
            
        $pod = $repository->find($id);
            
        if ($pod == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($pod);
            $em->flush();
                
            $data = [
                'message' => 'POD has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('pods');
    }
}
