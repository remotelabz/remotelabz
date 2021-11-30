<?php

namespace App\Service\Network;

use Exception;
use App\Repository\NetworkRepository;
use Remotelabz\NetworkBundle\Entity\IP;
use Remotelabz\NetworkBundle\Entity\Network;
use Remotelabz\NetworkBundle\Exception\BadNetmaskException;

/**
 * Subnets and IP attribution service.
 * 
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class NetworkManager
{
    protected $baseNetwork;
    protected $baseNetworkNetmask;
    protected $labNetworkNetmask;
    protected $networkRepository;

    public function __construct(
        string $baseNetwork,
        string $baseNetworkNetmask,
        string $labNetworkNetmask,
        NetworkRepository $networkRepository
    ) {
        $this->baseNetwork = $baseNetwork;
        $this->baseNetworkNetmask = $baseNetworkNetmask;
        $this->labNetworkNetmask = $labNetworkNetmask;
        $this->networkRepository = $networkRepository;
    }

    /**
     * Return a free subnetwork.
     *
     * @return Network|null
     */
    public function getAvailableSubnet(): ?Network
    {
        $baseNetwork = new Network($this->baseNetwork, $this->baseNetworkNetmask);
        $reservedNetworks = array_map("strval", $this->networkRepository->findAll());

        $next = new Network($baseNetwork->getIp(), new IP($this->labNetworkNetmask));
        $selected = null;
        while (!$selected) {
            $isReserved = array_search((string) $next, $reservedNetworks) !== false;
            if ($isReserved) {
                $next = $next->getNextNetwork();
            } else {
                $selected = $next;
            }
        }
        return $selected;
    }

}
