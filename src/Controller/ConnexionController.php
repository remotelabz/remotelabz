<?php

namespace App\Controller;

use App\Entity\Connexion;

use App\Form\ConnexionType;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ConnexionController extends AppController
{
    /**
     * @Route("/admin/connexions", name="connexions")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:Connexion');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByNameLike($search);
        } else {
            $data = $repository->findAll();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('connexion/index.html.twig', [
            'connexions' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/connexions/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_connexion",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Connexion');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('connexion/view.html.twig', [
            'connexion' => $data
        ]);
    }

    /**
     * @Route("/admin/connexions/new", name="new_connexion")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $connexion = new Connexion();
        $connexionForm = $this->createForm(ConnexionType::class, $connexion);
        $connexionForm->handleRequest($request);
        
        if ($connexionForm->isSubmitted() && $connexionForm->isValid()) {
            $connexion = $connexionForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($connexion);
            $entityManager->flush();
            
            $this->addFlash('success', 'Connexion has been created.');

            return $this->redirectToRoute('connexions');
        }
        
        return $this->render('connexion/new.html.twig', [
            'connexionForm' => $connexionForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/connexions/{id<\d+>}/edit", name="edit_connexion", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:Connexion');

        $connexion = $repository->find($id);

        if (null === $connexion) {
            throw new NotFoundHttpException();
        }

        $connexionForm = $this->createForm(ConnexionType::class, $connexion);
        $connexionForm->handleRequest($request);
        
        if ($connexionForm->isSubmitted() && $connexionForm->isValid()) {
            $connexion = $connexionForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($connexion);
            $entityManager->flush();
            
            $this->addFlash('success', 'Connexion has been edited.');

            return $this->redirectToRoute('show_connexion', [
                'id' => $id
            ]);
        }
        
        return $this->render('connexion/new.html.twig', [
            'connexionForm' => $connexionForm->createView(),
            'id' => $id,
            'name' => $connexion->getName()
        ]);
    }
        
    /**
     * @Route("/admin/connexions/{id<\d+>}", name="delete_connexion", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Connexion');
            
        $data = null;
        $status = 200;
            
        $connexion = $repository->find($id);
            
        if ($connexion == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($connexion);
            $em->flush();
                
            $data = [
                'message' => 'Connexion has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('connexions');
    }
}
