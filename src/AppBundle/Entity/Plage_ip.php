<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * plage_ip
 *
 * @ORM\Table(name="Plage_ip")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\Plage_ipRepository")
 */
class Plage_ip
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
     * @var network
     *
     * @ORM\Column(name="netowrk", type="string", length=30)
     */
    private $network;

    /**
     * @var string
     *
     * @ORM\Column(name="mask", type="string", length=30)
     */
    private $mask;

    /**
     * @var string
     *
     * @ORM\Column(name="gateway", type="string", length=30, nullable=true)
     */
    private $gateway;


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
     * Set string
     *
     * @param string $string
     *
     * @return plage_ip
     */
    public function setString($string)
    {
        $this->string = $string;

        return $this;
    }

    /**
     * Get string
     *
     * @return string
     */
    public function getString()
    {
        return $this->string;
    }

    /**
     * Set mask
     *
     * @param string $mask
     *
     * @return plage_ip
     */
    public function setMask($mask)
    {
        $this->mask = $mask;

        return $this;
    }

    /**
     * Get mask
     *
     * @return string
     */
    public function getMask()
    {
        return $this->mask;
    }

    /**
     * Set gateway
     *
     * @param string $gateway
     *
     * @return plage_ip
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;

        return $this;
    }

    /**
     * Get gateway
     *
     * @return string
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * Set network
     *
     * @param string $network
     *
     * @return Plage_ip
     */
    public function setNetwork($network)
    {
        $this->network = $network;

        return $this;
    }

    /**
     * Get network
     *
     * @return string
     */
    public function getNetwork()
    {
        return $this->network;
    }
}
