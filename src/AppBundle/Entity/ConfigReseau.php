<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ConfigReseau
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ConfigReseauRepository")
 */
class ConfigReseau
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
     * @ORM\Column(name="IP", type="string", length=255,nullable=true)
	 * @Assert\Ip
     */
    private $IP;

    /**
     * @var string
     *
     * @ORM\Column(name="IPv6", type="string", length=255, nullable=true)
	 * @Assert\Ip(version = "6")
     */
    private $IPv6;
	
	/**
     * @var string
		*
     * @ORM\Column(name="Prefix", type="string", length=255, nullable=true)
     */
    private $Prefix;

    /**
     * @var string
     *
     * @ORM\Column(name="IP_DNS", type="string", length=255, nullable=true)
	 * @Assert\Ip
     */
    private $IPDNS;

    /**
     * @var string
     *
     * @ORM\Column(name="IP_Gateway", type="string", length=255, nullable=true)
	 * @Assert\IP
     */
    private $IPGateway;

    /**
     * @var string
     *
     * @ORM\Column(name="Masque", type="string", length=255,nullable=true)
	 * @Assert\Ip
     */
    private $Masque;

    /**
     * @var string
     *
     * @ORM\Column(name="Protocole", type="string", length=255, nullable=true)
     */
    private $protocole;
	
	/**
     * @var string
     * @ORM\Column(name="Port", type="string", nullable=true)
     * @Assert\Regex(pattern="/^[\d]*$/")
     */
    private $port;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set iP
     *
     * @param string $iP
     *
     * @return ConfigReseau
     */
    public function setIP($iP)
    {
        $this->IP = $iP;

        return $this;
    }

    /**
     * Get iP
     *
     * @return string
     */
    public function getIP()
    {
        return $this->IP;
    }

    /**
     * Set iPv6
     *
     * @param string $iPv6
     *
     * @return ConfigReseau
     */
    public function setIPv6($iPv6)
    {
        $this->IPv6 = $iPv6;

        return $this;
    }

    /**
     * Get iPv6
     *
     * @return string
     */
    public function getIPv6()
    {
        return $this->IPv6;
    }

    /**
     * Set prefix
     *
     * @param string $prefix
     *
     * @return ConfigReseau
     */
    public function setPrefix($prefix)
    {
        $this->Prefix = $prefix;

        return $this;
    }

    /**
     * Get prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->Prefix;
    }

    /**
     * Set iPDNS
     *
     * @param string $iPDNS
     *
     * @return ConfigReseau
     */
    public function setIPDNS($iPDNS)
    {
        $this->IPDNS = $iPDNS;

        return $this;
    }

    /**
     * Get iPDNS
     *
     * @return string
     */
    public function getIPDNS()
    {
        return $this->IPDNS;
    }

    /**
     * Set iPGateway
     *
     * @param string $iPGateway
     *
     * @return ConfigReseau
     */
    public function setIPGateway($iPGateway)
    {
        $this->IPGateway = $iPGateway;

        return $this;
    }

    /**
     * Get iPGateway
     *
     * @return string
     */
    public function getIPGateway()
    {
        return $this->IPGateway;
    }

    /**
     * Set masque
     *
     * @param string $masque
     *
     * @return ConfigReseau
     */
    public function setMasque($masque)
    {
        $this->Masque = $masque;

        return $this;
    }

    /**
     * Get masque
     *
     * @return string
     */
    public function getMasque()
    {
        return $this->Masque;
    }

    /**
     * Set protocole
     *
     * @param string $protocole
     *
     * @return ConfigReseau
     */
    public function setProtocole($protocole)
    {
        $this->protocole = $protocole;

        return $this;
    }

    /**
     * Get protocole
     *
     * @return string
     */
    public function getProtocole()
    {
        return $this->protocole;
    }

    /**
     * Set port
     *
     * @param string $port
     *
     * @return ConfigReseau
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * Get port
     *
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }
}
