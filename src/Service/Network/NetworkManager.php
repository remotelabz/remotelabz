<?php

namespace App\Service\Network;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use App\Repository\NetworkRepository;
use Remotelabz\NetworkBundle\Entity\IP;
use Remotelabz\NetworkBundle\Entity\Network;
use Remotelabz\NetworkBundle\Exception\BadNetmaskException;
use Remotelabz\NetworkBundle\Exception\NoNetworkAvailableException;

/**
 * Subnets and IP attribution service.
 *
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class NetworkManager
{
    /**
     * Maximum number of allocation attempts when a concurrent process commits
     * the very same subnet between our table read and our INSERT.
     */
    private const MAX_ALLOCATION_ATTEMPTS = 3;

    protected $baseNetwork;
    protected $baseNetworkNetmask;
    protected $labNetworkNetmask;
    protected $networkRepository;
    protected $entityManager;

    public function __construct(
        string $baseNetwork,
        string $baseNetworkNetmask,
        string $labNetworkNetmask,
        NetworkRepository $networkRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->baseNetwork = $baseNetwork;
        $this->baseNetworkNetmask = $baseNetworkNetmask;
        $this->labNetworkNetmask = $labNetworkNetmask;
        $this->networkRepository = $networkRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Return a free subnetwork AND reserve it in the database.
     *
     * The chosen /28 is persisted and flushed right away, so the reservation
     * is committed before any other process can read the table. This is what
     * makes concurrent InstanceManager::create() calls safe: if two of them
     * pick the very same /28, the unique index (ip__long, netmask__long) on
     * the network table makes the loser's INSERT fail with a duplicate key
     * error, the failed entity is un-scheduled and the allocation is
     * retried with a fresh read of the table (the winner's row is now
     * visible, so the next /28 is picked).
     *
     * The returned Network is managed: attach it to the LabInstance being
     * created. If the rest of the creation fails, the caller must remove()
     * it so the subnet is not left reserved forever (the nightly cron also
     * sweeps unreferenced rows as a safety net).
     *
     * @return Network|null the allocated subnet, or null when the base
     *                      network is exhausted
     *
     * @throws NoNetworkAvailableException when the subnets keep conflicting
     *                                     with concurrent allocations
     */
    public function getAvailableSubnet(): ?Network
    {
        for ($attempt = 1; $attempt <= self::MAX_ALLOCATION_ATTEMPTS; $attempt++) {
            $network = $this->pickFreeSubnet();

            if (null === $network) {
                return null;
            }

            try {
                $this->entityManager->persist($network);
                // Commit the reservation immediately (ORM 3 flush() takes no
                // entity argument). At the only call site
                // (InstanceManager::create()) the lab instance is not
                // persisted yet, so this flush commits the network row on
                // its own; the instance is flushed later by the caller.
                $this->entityManager->flush();

                return $network;
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                // A concurrent allocation committed the very same subnet
                // between our table read and our INSERT. The failed entity
                // is still tracked by the UnitOfWork: remove() un-schedules
                // it (no SQL is executed, the row does not exist) so the
                // next scan starts from a clean state. The winner's row is
                // now committed, so the scan picks the following /28.
                $this->entityManager->remove($network);

                continue;
            }
        }

        throw new NoNetworkAvailableException(
            'Could not allocate a free lab subnet: ' . self::MAX_ALLOCATION_ATTEMPTS
            . ' attempts conflicted with concurrent allocations.'
        );
    }

    /**
     * Scan the base network and return the first /28 that is not reserved.
     *
     * Nothing is persisted: this is the pure computation part of
     * getAvailableSubnet(). Returns null when every subnet of the base
     * network is already reserved (the scan stops at the base network
     * boundary instead of walking on to the next /16).
     */
    private function pickFreeSubnet(): ?Network
    {
        $baseNetwork = new Network($this->baseNetwork, $this->baseNetworkNetmask);
        $reservedNetworks = array_map("strval", $this->networkRepository->findAll());

        $next = new Network($baseNetwork->getIp(), new IP($this->labNetworkNetmask));
        $baseStart = ip2long($baseNetwork->getIp()->getAddr());
        $baseSize = 1 << (32 - $baseNetwork->getCidrNetmask());

        while (ip2long($next->getIp()->getAddr()) < $baseStart + $baseSize) {
            if (array_search((string) $next, $reservedNetworks) === false) {
                return $next;
            }

            $next = $next->getNextNetwork();
        }

        return null;
    }
}
