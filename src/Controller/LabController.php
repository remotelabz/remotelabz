<?php

namespace App\Controller;

use App\Entity\Lab;
use App\Form\LabType;

use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class LabController extends AppController
{
    /**
     * @Route("/admin/labs", name="labs")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByNameLike($search);
        } else {
            $data = $repository->findAll();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('lab/index.html.twig', [
            'labs' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_lab",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('lab/view.html.twig', [
            'lab' => $data
        ]);
    }

    /**
     * @Route("/admin/labs/new", name="new_lab")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $lab = new Lab();
        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);
        
        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $lab = $labForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();
            
            $this->addFlash('success', 'Lab has been created.');

            return $this->redirectToRoute('labs');
        }
        
        return $this->render('lab/new.html.twig', [
            'labForm' => $labForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/edit", name="edit_lab", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $lab = $repository->find($id);

        if (null === $lab) {
            throw new NotFoundHttpException();
        }

        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);
        
        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $lab = $labForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();
            
            $this->addFlash('success', 'Lab has been edited.');

            return $this->redirectToRoute('show_lab', [
                'id' => $id
            ]);
        }
        
        return $this->render('lab/new.html.twig', [
            'labForm' => $labForm->createView(),
            'id' => $id,
            'name' => $lab->getName()
        ]);
    }
        
    /**
     * @Route("/admin/labs/{id<\d+>}", name="delete_lab", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
            
        $data = null;
        $status = 200;
            
        $lab = $repository->find($id);
            
        if ($lab == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($lab);
            $em->flush();
                
            $data = [
                'message' => 'Lab has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('labs');
    }
}
