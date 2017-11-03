<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * NetworkUsed
 *
 * @ORM\Table(name="network_used")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\NetworkUsedRepository")
 */
class NetworkUsed
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="ip_address", type="string", length=255, unique=true)
	 * @Assert\Ip
     */
    private $ipAddress;

    /**
	 * Notation /xx 
     * @var string
     * 
     * @ORM\Column(name="netmask", type="string", length=255)
     */
    private $netmask;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set ipAddress
     *
     * @param string $ipAddress
     *
     * @return NetworkUsed
     */
    public function setIpAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /**
     * Get ipAddress
     *
     * @return string
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * Set netmask
     *
     * @param string $netmask
     *
     * @return NetworkUsed
     */
    public function setNetmask($netmask)
    {
        $this->netmask = $netmask;

        return $this;
    }

    /**
     * Get netmask
     *
     * @return string
     */
    public function getNetmask()
    {
        return $this->netmask;
    }
}
