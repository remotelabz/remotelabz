<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\LabInstance;
use App\Instance\InstanceState;
use App\Repository\ConfigWorkerRepository;
use App\Repository\LabShareRepository;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\SecurityMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Computes and broadcasts the shared lab topology of a group.
 *
 * The topology is built from the LabShare rules of the group (see shared-labs-front-plan.md):
 * for each rule (S, L, G), every instance of S in G is linked to every instance of L in G.
 * One message carries the whole topology of one group, which is also the state key used
 * by the workers: a group without any link is sent with an empty link list so that the
 * workers purge their rules for that group.
 */
final class SharedLabSecurityManager
{
    private LabShareRepository $labShareRepository;
    private ConfigWorkerRepository $configWorkerRepository;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;

    public function __construct(
        LabShareRepository $labShareRepository,
        ConfigWorkerRepository $configWorkerRepository,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ) {
        $this->labShareRepository = $labShareRepository;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->bus = $bus;
        $this->logger = $logger;
    }

    /**
     * Build the topology of one group.
     *
     * @return array{group: string, links: array<int, array{a: array, b: array}>}
     */
    public function buildLinks(Group $group): array
    {
        $links = [];
        $seen = [];
        $instancesByLab = [];

        foreach ($this->labShareRepository->findBy(['group' => $group]) as $share) {
            $labA = $share->getLab();
            $labB = $share->getSharedWith();

            if (is_null($labA) || is_null($labB)) {
                continue;
            }

            $instancesA = $this->eligibleInstances($group, $labA, $instancesByLab);
            $instancesB = $this->eligibleInstances($group, $labB, $instancesByLab);

            foreach ($instancesA as $instanceA) {
                foreach ($instancesB as $instanceB) {
                    if ($instanceA->getUuid() === $instanceB->getUuid()) {
                        continue;
                    }

                    $key = $this->linkKey($instanceA, $instanceB);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $links[] = [
                        'a' => $this->describe($instanceA),
                        'b' => $this->describe($instanceB),
                    ];
                }
            }
        }

        return [
            'group' => (string) $group->getUuid(),
            'links' => $links,
        ];
    }

    /**
     * Broadcast the topology of one group to every available worker.
     */
    public function syncGroup(Group $group): void
    {
        $payload = json_encode($this->buildLinks($group), JSON_UNESCAPED_SLASHES);

        if (false === $payload) {
            $this->logger->error('[SharedLabSecurityManager:syncGroup]::Could not encode the topology of group '.$group->getPath());

            return;
        }

        $workers = $this->configWorkerRepository->findAll();

        foreach ($workers as $worker) {
            $workerIp = $worker->getIPv4();

            if (empty($workerIp) || !$worker->getAvailable()) {
                continue;
            }

            $this->bus->dispatch(
                new SecurityMessage($payload, (string) $group->getUuid()), [
                    new AmqpStamp($workerIp, AMQP_NOPARAM, []),
                ]
            );
        }

        $this->logger->info('[SharedLabSecurityManager:syncGroup]::Shared lab topology of group '.$group->getPath().' sent to '.count($workers).' worker(s)');
    }

    /**
     * Broadcast the topology of several groups (one message per group).
     *
     * @param iterable<Group> $groups
     */
    public function syncGroups(iterable $groups): void
    {
        foreach ($groups as $group) {
            $this->syncGroup($group);
        }
    }

    /**
     * Instances of a lab taking part in the topology of a group:
     * placed on a worker, virtual, group-owned and with at least one device
     * starting or started.
     *
     * @param array<int|string, LabInstance[]> $cache
     *
     * @return LabInstance[]
     */
    private function eligibleInstances(Group $group, Lab $lab, array &$cache): array
    {
        $key = $lab->getId() ?? spl_object_id($lab);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $instances = [];

        if ($lab->getVirtuality() != 1) {
            return $cache[$key] = $instances;
        }

        foreach ($group->getLabInstances() as $labInstance) {
            if (!$this->sameLab($labInstance->getLab(), $lab)) {
                continue;
            }
            if ($labInstance->getOwnedBy() !== LabInstance::OWNED_BY_GROUP) {
                continue;
            }
            if (empty($labInstance->getWorkerIp())) {
                continue;
            }
            if (is_null($labInstance->getNetwork())) {
                continue;
            }
            if (!$this->hasRunningDevice($labInstance)) {
                continue;
            }

            $instances[] = $labInstance;
        }

        return $cache[$key] = $instances;
    }

    private function hasRunningDevice(LabInstance $labInstance): bool
    {
        foreach ($labInstance->getDeviceInstances() as $deviceInstance) {
            if (in_array($deviceInstance->getState(), [InstanceState::STARTING, InstanceState::STARTED], true)) {
                return true;
            }
        }

        return false;
    }

    private function sameLab(?Lab $instanceLab, Lab $lab): bool
    {
        if (is_null($instanceLab)) {
            return false;
        }

        if ($instanceLab === $lab) {
            return true;
        }

        return !is_null($instanceLab->getId()) && $instanceLab->getId() === $lab->getId();
    }

    private function describe(LabInstance $labInstance): array
    {
        return [
            'uuid' => $labInstance->getUuid(),
            'network' => (string) $labInstance->getNetwork(),
            'workerIp' => (string) $labInstance->getWorkerIp(),
        ];
    }

    private function linkKey(LabInstance $instanceA, LabInstance $instanceB): string
    {
        $uuids = [$instanceA->getUuid(), $instanceB->getUuid()];
        sort($uuids);

        return $uuids[0].'|'.$uuids[1];
    }
}
