<?php

namespace App\Controller;

use App\Entity\ControlProtocolType;
use Psr\Log\LoggerInterface;
use App\Form\ControlProtocolTypeType;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\ControlProtocolTypeRepository;
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

class ControlProtocolTypeController extends Controller
{
    /**
     * @var ControlProtocolTypeRepository
     */
    private $controlProtocolTypeRepository;
    private $logger;
    private $serializer;
    protected $bus;

    public function __construct(LoggerInterface $logger,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        SerializerInterface $serializerInterface,
        MessageBusInterface $bus,
        EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->serializer = $serializerInterface;
        $this->bus = $bus;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/controlProtocolType', name: 'api_controlProtocolType')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/controlProtocolType', name: 'controlProtocolType')]
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->orderBy([
                'name' => Criteria::ASC
            ]);

        $controlProtocolType = $this->controlProtocolTypeRepository->matching($criteria)->getValues();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($controlProtocolType, 200, [], ['api_get_controlProtocolType']);
        }

        return $this->render('control_protocol_type/index.html.twig', [
            'controlProtocolType' => $controlProtocolType,
            'search' => $search
        ]);
    }

    
	#[Get('/api/controlProtocolType/{id<\d+>}', name: 'api_get_controlProtocolType')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/controlProtocolType/{id<\d+>}', name: 'show_controlProtocolType')]
    public function showAction(Request $request, int $id)
    {

        if (!$controlProtocolType = $this->controlProtocolTypeRepository->find($id)) {
            throw new NotFoundHttpException("Control Protocol " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($controlProtocolType, 200, [], [$request->get('_route')]);
        }

        $name=$controlProtocolType->getName();
        

        return $this->render('control_protocol_type/view.html.twig', [         
            'controlProtocolType' => $controlProtocolType
        ]);
    }

    #[Route(path: '/admin/controlProtocolType/new', name: 'new_controlProtocolType')]
    public function newAction(Request $request)
    {
        $controlProtocolType = new ControlProtocolType();
        $controlProtocolTypeForm = $this->createForm(controlProtocolTypeType::class, $controlProtocolType);
        $controlProtocolTypeForm->handleRequest($request);

        if ($controlProtocolTypeForm->isSubmitted() && $controlProtocolTypeForm->isValid()) {
            /** @var ControlProtocolType $controlProtocolType */

            $controlProtocolType = $controlProtocolTypeForm->getData();
            $entityManager = $this->entityManager;
            $entityManager->persist($controlProtocolType);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 201, [], ['api_get_controlProtocolType']);
            }

            $this->addFlash('success', 'Control Protocol has been created.');

            return $this->redirectToRoute('controlProtocolType');

        }

        return $this->render('control_protocol_type/new.html.twig', [
            'controlProtocolTypeForm' => $controlProtocolTypeForm->createView(),
        ]);
    }

    #[Route(path: '/admin/controlProtocolType/{id<\d+>}/edit', name: 'edit_controlProtocolType', methods: ['GET', 'POST'])]
    public function editAction(Request $request, int $id)
    {
        $controlProtocolType = $this->controlProtocolTypeRepository->find($id);
        if (null === $controlProtocolType) {
            throw new NotFoundHttpException("Control Protocol " . $id . " does not exist.");
        }

        $controlProtocolTypeForm = $this->createForm(controlProtocolTypeType::class, $controlProtocolType);
        $controlProtocolTypeForm->handleRequest($request);

        if ($controlProtocolTypeForm->isSubmitted() && $controlProtocolTypeForm->isValid()) {
            $controlProtocolType = $controlProtocolTypeForm->getData();
            
            $entityManager = $this->entityManager;
            $entityManager->persist($controlProtocolType);
            $entityManager->flush();

            $this->addFlash('success', 'Control Protocol has been edited.');
            
            return $this->redirectToRoute('show_controlProtocolType', [
                        'id' => $id
            ]);

        }

        return $this->render('control_protocol_type/new.html.twig', [
            'controlProtocolType' => $controlProtocolType,
            'controlProtocolTypeForm' => $controlProtocolTypeForm->createView()
        ]);
    }

    #[Route(path: '/admin/controlProtocolType/{id<\d+>}/delete', name: 'delete_controlProtocolType', methods: 'GET')]
    public function deleteAction($id)
    {
        $controlProtocolType = $this->controlProtocolTypeRepository->find($id);
      

        if (null === $controlProtocolType) {
            throw new NotFoundHttpException("Control Protocol " . $id . " does not exist.");
        }
     

        $entityManager = $this->entityManager;
        $entityManager->remove($controlProtocolType);

        try {
            $entityManager->flush();

            $this->addFlash('success', $controlProtocolType->getName() . ' has been deleted.');

            return $this->redirectToRoute('controlProtocolType');
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error("ForeignKeyConstraintViolationException".$e->getMessage());
            $this->addFlash('danger', 'This control protocol is still used in some device templates or operating system. Please delete them first.');

            return $this->redirectToRoute('show_controlProtocolType', [
                'id' => $id
            ]);
        }
    }

}
