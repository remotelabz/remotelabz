<?php

namespace App\Controller;

use App\Entity\JitsiCall;
use App\Repository\JitsiCallRepository;
use App\Repository\LabRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\GroupRepository;
use Remotelabz\Message\Message\InstanceStateMessage;
use App\Service\JitsiJWTCreator;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\ORM\EntityManagerInterface;

class JitsiCallController extends Controller
{
    private $jitsiCallRepository;

    public function __construct(JitsiCallRepository $jitsiCallRepository, EntityManagerInterface $entityManager)
    {
        $this->jitsiCallRepository = $jitsiCallRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/jitsi-call/{labUuid}/{groupUuid}/start', name: 'api_start_jitsi_call')]
    public function startJitsiCall(
        Request $request,
        string $labUuid,
        string $groupUuid,
        GroupRepository $groupRepository,
        LabRepository $labRepository,
        LabInstanceRepository $labInstanceRepository
    ) {
        if (!$group = $groupRepository->findOneBy(['uuid' => $groupUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$lab = $labRepository->findOneBy(['uuid' => $labUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$labInstance = $labInstanceRepository->findOneBy(['_group' => $group, 'lab' => $lab])) {
            throw new NotFoundHttpException();
        }

        $jitsiCall = $labInstance->getJitsiCall();

        if ($jitsiCall->isStarted()) {
            throw new AccessDeniedHttpException();
        }

        $jitsiCall->setState(InstanceStateMessage::STATE_STARTED);
        $entityManager = $this->entityManager;
        $entityManager->persist($jitsiCall);
        $entityManager->flush();

        return $this->json();
    }

    
	#[Get('/api/jitsi-call/{labUuid}/{groupUuid}/join', name: 'api_join_jitsi_call')]
    public function joinJitsiCall(
        Request $request,
        string $labUuid,
        string $groupUuid,
        GroupRepository $groupRepository,
        LabRepository $labRepository,
        LabInstanceRepository $labInstanceRepository,
        JitsiJWTCreator $jitsiJWTCreator
    ) {
        if (!$group = $groupRepository->findOneBy(['uuid' => $groupUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$lab = $labRepository->findOneBy(['uuid' => $labUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$labInstance = $labInstanceRepository->findOneBy(['_group' => $group, 'lab' => $lab])) {
            throw new NotFoundHttpException();
        }

        if (!$labInstance->getJitsiCall()->isStarted()) {
            throw new AccessDeniedHttpException();
        }

        $user = $this->getUser();

        $email = $user->getEmail();
        $name = $user->getName();

        $url = $jitsiJWTCreator->getToken($name, $email, $group->getName(), $lab->getName());

        return $this->json($url, 200, []);
    }
}