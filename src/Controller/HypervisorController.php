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
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Remotelabz\Message\Message\InstanceActionMessage;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

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
        MessageBusInterface $bus,
        EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->serializer = $serializerInterface;
        $this->bus = $bus;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/hypervisor', name: 'api_hypervisor')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/hypervisor', name: 'hypervisor')]
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

    
	#[Get('/api/hypervisor/{id<\d+>}', name: 'api_get_hypervisor')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/hypervisor/{id<\d+>}', name: 'show_hypervisor')]
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

    #[Route(path: '/admin/hypervisor/new', name: 'new_hypervisor')]
    public function newAction(Request $request)
    {
        $hypervisor = new Hypervisor();
        $hypervisorForm = $this->createForm(HypervisorType::class, $hypervisor);
        $hypervisorForm->handleRequest($request);

        if ($hypervisorForm->isSubmitted() && $hypervisorForm->isValid()) {
            /** @var Hypervisor $hypervisor */

            $hypervisor = $hypervisorForm->getData();
            $entityManager = $this->entityManager;
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

    #[Route(path: '/admin/hypervisor/{id<\d+>}/edit', name: 'edit_hypervisor', methods: ['GET', 'POST'])]
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
            
            $entityManager = $this->entityManager;
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

    #[Route(path: '/admin/hypervisor/{id<\d+>}/delete', name: 'delete_hypervisor', methods: 'GET')]
    public function deleteAction($id)
    {
        $hypervisor = $this->hypervisorRepository->find($id);
      

        if (null === $hypervisor) {
            throw new NotFoundHttpException("Hypervisor " . $id . " does not exist.");
        }
     

        $entityManager = $this->entityManager;
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
