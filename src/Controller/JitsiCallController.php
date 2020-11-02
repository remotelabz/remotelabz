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
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class JitsiCallController extends Controller
{
    private $jitsiCallRepository;

    public function __construct(JitsiCallRepository $jitsiCallRepository)
    {
        $this->jitsiCallRepository = $jitsiCallRepository;
    }

    /**
     * @Rest\Get("/api/jitsi-call/{labUuid}/{groupUuid}/start", name="api_start_jitsi_call")
     */
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
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($jitsiCall);
        $entityManager->flush();

        return $this->json();
    }

    /**
     * @Rest\Get("/api/jitsi-call/{labUuid}/{groupUuid}/join", name="api_join_jitsi_call")
     */
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