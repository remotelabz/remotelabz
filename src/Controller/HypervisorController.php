<?php

namespace App\Controller;

use App\Entity\Hypervisor;
use Psr\Log\LoggerInterface;
use App\Form\HypervisorType;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\HypervisorRepository;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class HypervisorController extends Controller
{
    /**
     * @var HypervisorRepository
     */
    private $hypervisorRepository;
    private $logger;
    private $serializer;
    protected $bus;

    public function __construct(LoggerInterface $logger,
        HypervisorRepository $hypervisorRepository,
        SerializerInterface $serializerInterface,
        MessageBusInterface $bus)
    {
        $this->logger = $logger;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->serializer = $serializerInterface;
        $this->bus = $bus;

    }

    /**
     * @Route("/admin/hypervisor", name="hypervisor")
     * 
     * @Rest\Get("/api/hypervisor", name="api_hypervisor")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->orderBy([
                'name' => Criteria::ASC
            ]);

        $hypervisor = $this->hypervisorRepository->matching($criteria)->getValues();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($hypervisor, 200, [], ['api_get_hypervisor']);
        }

        return $this->render('hypervisor/index.html.twig', [
            'hypervisor' => $hypervisor,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/hypervisor/{id<\d+>}", name="show_hypervisor")
     * 
     * @Rest\Get("/api/hypervisor/{id<\d+>}", name="api_get_hypervisor")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
    public function showAction(Request $request, int $id)
    {

        if (!$hypervisor = $this->hypervisorRepository->find($id)) {
            throw new NotFoundHttpException("Hypervisor " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($hypervisor, 200, [], [$request->get('_route')]);
        }

        $name=$hypervisor->getName();
        

        return $this->render('hypervisor/view.html.twig', [         
            'hypervisor' => $hypervisor
        ]);
    }

    /**
     * @Route("/admin/hypervisor/new", name="new_hypervisor")
     */
    public function newAction(Request $request)
    {
        $hypervisor = new Hypervisor();
        $hypervisorForm = $this->createForm(HypervisorType::class, $hypervisor);
        $hypervisorForm->handleRequest($request);

        if ($hypervisorForm->isSubmitted() && $hypervisorForm->isValid()) {
            /** @var Hypervisor $hypervisor */

            $hypervisor = $hypervisorForm->getData();
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($hypervisor);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 201, [], ['api_get_hypervisor']);
            }

            $this->addFlash('success', 'Hypervisor has been created.');

            return $this->redirectToRoute('hypervisor');

        }

        return $this->render('hypervisor/new.html.twig', [
            'hypervisorForm' => $hypervisorForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/hypervisor/{id<\d+>}/edit", name="edit_hypervisor", methods={"GET", "POST"})
     */
    public function editAction(Request $request, int $id)
    {
        $hypervisor = $this->hypervisorRepository->find($id);
        if (null === $hypervisor) {
            throw new NotFoundHttpException("Hypervisor " . $id . " does not exist.");
        }

        $hypervisorForm = $this->createForm(HypervisorType::class, $hypervisor);
        $hypervisorForm->handleRequest($request);

        if ($hypervisorForm->isSubmitted() && $hypervisorForm->isValid()) {
            $hypervisor = $hypervisorForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($hypervisor);
            $entityManager->flush();

            $this->addFlash('success', 'Hypervisor has been edited.');
            
            return $this->redirectToRoute('show_hypervisor', [
                        'id' => $id
            ]);

        }

        return $this->render('hypervisor/new.html.twig', [
            'hypervisor' => $hypervisor,
            'hypervisorForm' => $hypervisorForm->createView()
        ]);
    }

    /**
     * @Route("/admin/hypervisor/{id<\d+>}/delete", name="delete_hypervisor", methods="GET")
     */
    public function deleteAction($id)
    {
        $hypervisor = $this->hypervisorRepository->find($id);
      

        if (null === $hypervisor) {
            throw new NotFoundHttpException("Hypervisor " . $id . " does not exist.");
        }
     

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($hypervisor);

        try {
            $entityManager->flush();

            $this->addFlash('success', $hypervisor->getName() . ' has been deleted.');

            return $this->redirectToRoute('hypervisor');
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error("ForeignKeyConstraintViolationException".$e->getMessage());
            $this->addFlash('danger', 'This hypervisor is still used in some device templates or operating system. Please delete them first.');

            return $this->redirectToRoute('show_hypervisor', [
                'id' => $id
            ]);
        }
    }

}
