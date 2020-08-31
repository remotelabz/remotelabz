<?php

namespace App\Service\Network;

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
    protected $networkRepository;

    public function __construct(NetworkRepository $networkRepository)
    {
        $this->networkRepository = $networkRepository;
    }

    /**
     * Return a free subnetwork.
     *
     * @return Network|null
     */
    public function getAvailableSubnet(): ?Network
    {
        $baseNetwork = self::getBaseNetwork();
        $reservedNetworks = array_map("strval", $this->networkRepository->findAll());

        $next = new Network($baseNetwork->getIp(), self::getSplitNetmask());
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

    // /**
    //  * Return a free IP from the provided network.
    //  * 
    //  * @param Network $network The network to get an IP from
    //  * @return IP|null Returns `null` if no IP is available within the network, returns the IP otherwise
    //  */
    // public function getAvailableIp(Network $network): ?IP
    // {
    //     $network->
    // }

    /**
     * Get the base network for all labs from `.env` or environment variables.
     */
    public static function getBaseNetwork(): Network
    {
        return new Network(getenv('BASE_NETWORK'), getenv('BASE_NETWORK_NETMASK'));
    }

    /**
     * Get the netmask used to split the base network from `.env` or environment variables.
     */
    public static function getSplitNetmask(): IP
    {
        $ip = new IP(getenv('LAB_NETWORK_NETMASK'));
        if (!$ip->isNetmask())
            throw new BadNetmaskException();

        return $ip;
    }
}
