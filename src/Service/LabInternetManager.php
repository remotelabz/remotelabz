<?php

namespace App\Service;

use App\Entity\Lab;
use App\Repository\LabInstanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\InstanceActionMessage;
use Remotelabz\Message\Message\InstanceStateMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Connects or disconnects every instance of a lab from the Internet.
 *
 * The Internet of a lab is provided by the worker: the lab bridge is linked to
 * the Internet interface and a MASQUERADE rule is added on the worker (see
 * InstanceManager::connectToInternet/disconnectFromInternet on the worker side).
 * This service does not talk to the worker over HTTP anymore: it dispatches an
 * InstanceActionMessage (ACTION_CONNECT / ACTION_DISCONNECT) routed to the
 * worker hosting each instance, exactly like the other instance actions.
 *
 * The lab-level flag Lab::isInternetAuthorized is only the permission: it says
 * whether the lab may reach the Internet at all (and therefore whether it is
 * connected automatically at launch, see LabLaunchRequestMessageHandler). It is
 * written by the lab form only, never by the Connect/Disconnect buttons: those
 * only flip the real state LabInstance::isInternetConnected, so a lab stays
 * internet-authorized after a manual disconnection.
 */
final class LabInternetManager
{
    private LabInstanceRepository $labInstanceRepository;
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;

    private array $workerSerializationGroups = [
        'worker'
    ];

    public function __construct(
        LabInstanceRepository $labInstanceRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ) {
        $this->labInstanceRepository = $labInstanceRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->bus = $bus;
        $this->logger = $logger;
    }

    /**
     * Connect every instance of the lab to the Internet.
     */
    public function connectLab(Lab $lab): void
    {
        $this->toggleLab($lab, true);
    }

    /**
     * Disconnect every instance of the lab from the Internet.
     */
    public function disconnectLab(Lab $lab): void
    {
        $this->toggleLab($lab, false);
    }

    /**
     * Propagate the wanted Internet state to every existing instance placed on
     * a worker. The lab flag itself is left untouched: it is the permission and
     * is only written by the lab form (LabController::updateActionTest).
     *
     * Instances not placed yet (no workerIp) are left untouched: they follow
     * the lab flag at launch (LabLaunchRequestMessageHandler). Instances being
     * deleted or already deleted are skipped, and instances in error state are
     * skipped as well since their bridge may not exist on the worker.
     */
    private function toggleLab(Lab $lab, bool $connect): void
    {
        $action = $connect
            ? InstanceActionMessage::ACTION_CONNECT
            : InstanceActionMessage::ACTION_DISCONNECT;

        foreach ($this->labInstanceRepository->findByLab($lab) as $labInstance) {
            if (is_null($labInstance->getWorkerIp())) {
                continue;
            }

            $state = $labInstance->getState();
            if (in_array($state, [
                InstanceStateMessage::STATE_DELETING,
                InstanceStateMessage::STATE_DELETED,
                InstanceStateMessage::STATE_ERROR,
            ], true)) {
                continue;
            }

            $context = SerializationContext::create()->setGroups($this->workerSerializationGroups);
            $labJson = $this->serializer->serialize($labInstance, 'json', $context);

            $this->bus->dispatch(
                new InstanceActionMessage($labJson, $labInstance->getUuid(), $action),
                [new AmqpStamp($labInstance->getWorkerIp(), AMQP_NOPARAM, [])]
            );

            $labInstance->setInternetConnected($connect);
            $this->entityManager->persist($labInstance);

            $this->logger->info(
                '[LabInternetManager:toggleLab]::'.$action.' message sent for lab instance '
                .$labInstance->getUuid().' on worker '.$labInstance->getWorkerIp()
            );
        }

        $this->entityManager->flush();
    }
}
