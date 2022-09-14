<?php

namespace App\Controller;

use App\Entity\ControlProtocol;
use Psr\Log\LoggerInterface;
use App\Form\ControlProtocolType;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\ControlProtocolRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Remotelabz\Message\Message\InstanceActionMessage;

class ControlProtocolController extends Controller
{
    /**
     * @var ControlProtocolRepository
     */
    private $controlProtocolRepository;
    private $logger;
    private $serializer;
    protected $bus;

    public function __construct(LoggerInterface $logger,
        ControlProtocolRepository $controlProtocolRepository,
        SerializerInterface $serializerInterface,
        MessageBusInterface $bus)
    {
        $this->logger = $logger;
        $this->controlProtocolRepository = $controlProtocolRepository;
        $this->serializer = $serializerInterface;
        $this->bus = $bus;

    }

    /**
     * @Route("/admin/controlProtocol", name="controlProtocol")
     * 
     * @Rest\Get("/api/controlProtocol", name="api_controlProtocol")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->orderBy([
                'id' => Criteria::DESC
            ]);

        $controlProtocol = $this->controlProtocolRepository->matching($criteria)->getValues();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($controlProtocol, 200, [], ['api_get_controlProtocol']);
        }

        return $this->render('controlProtocol/index.html.twig', [
            'controlProtocol' => $controlProtocol,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/controlProtocol/{id<\d+>}", name="show_controlProtocol")
     * 
     * @Rest\Get("/api/controlProtocol/{id<\d+>}", name="api_get_controlProtocol")
     */
    public function showAction(Request $request, int $id)
    {

        if (!$controlProtocol = $this->controlProtocolRepository->find($id)) {
            throw new NotFoundHttpException("Control Protocol " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($controlProtocol, 200, [], [$request->get('_route')]);
        }

        $name=$controlProtocol->getName();
        

        return $this->render('controlProtocol/view.html.twig', [         
            'controlProtocol' => $controlProtocol
        ]);
    }

    /**
     * @Route("/admin/controlProtocol/new", name="new_controlProtocol")
     */
    public function newAction(Request $request)
    {
        $controlProtocol = new ControlProtocol();
        $controlProtocolForm = $this->createForm(controlProtocolType::class, $controlProtocol);
        $controlProtocolForm->handleRequest($request);

        if ($controlProtocolForm->isSubmitted() && $controlProtocolForm->isValid()) {
            /** @var ControlProtocol $controlProtocol */

            $controlProtocol = $controlProtocolForm->getData();
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($controlProtocol);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 201, [], ['api_get_controlProtocol']);
            }

            $this->addFlash('success', 'Control Protocol has been created.');

            return $this->redirectToRoute('controlProtocol');

        }

        return $this->render('controlProtocol/new.html.twig', [
            'controlProtocolForm' => $controlProtocolForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/controlProtocol/{id<\d+>}/edit", name="edit_controlProtocol", methods={"GET", "POST"})
     */
    public function editAction(Request $request, int $id)
    {
        $controlProtocol = $this->controlProtocolRepository->find($id);
        if (null === $controlProtocol) {
            throw new NotFoundHttpException("Control Protocol " . $id . " does not exist.");
        }

        $controlProtocolForm = $this->createForm(controlProtocolType::class, $controlProtocol);
        $controlProtocolForm->handleRequest($request);

        if ($controlProtocolForm->isSubmitted() && $controlProtocolForm->isValid()) {
            $controlProtocol = $controlProtocolForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($controlProtocol);
            $entityManager->flush();

            $this->addFlash('success', 'Control Protocol has been edited.');
            
            return $this->redirectToRoute('show_controlProtocol', [
                        'id' => $id
            ]);

        }

        return $this->render('controlProtocol/new.html.twig', [
            'controlProtocol' => $controlProtocol,
            'controlProtocolForm' => $controlProtocolForm->createView()
        ]);
    }

    /**
     * @Route("/admin/controlProtocol/{id<\d+>}/delete", name="delete_controlProtocol", methods="GET")
     */
    public function deleteAction($id)
    {
        $controlProtocol = $this->controlProtocolRepository->find($id);
      

        if (null === $controlProtocol) {
            throw new NotFoundHttpException("Control Protocol " . $id . " does not exist.");
        }
     

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($controlProtocol);

        try {
            $entityManager->flush();

            $this->addFlash('success', $controlProtocol->getName() . ' has been deleted.');

            return $this->redirectToRoute('controlProtocol');
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error("ForeignKeyConstraintViolationException".$e->getMessage());
            $this->addFlash('danger', 'This control protocol is still used in some device templates or operating system. Please delete them first.');

            return $this->redirectToRoute('show_controlProtocol', [
                'id' => $id
            ]);
        }
    }

}
