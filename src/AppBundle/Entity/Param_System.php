<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Param_System
 * Paramètre du système de virtualisation 
 *
 * @ORM\Table(name="param__system")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\Param_SystemRepository")
 */
class Param_System
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
     * @var int
     *
     * @ORM\Column(name="VNC_Port_min", type="integer")
     */
    private $VNCPortMin;

    /**
     * @var int
     *
     * @ORM\Column(name="VNC_Port_max", type="integer")
     */
    private $VNCPortMax;

    /**
     * @var int
     *
     * @ORM\Column(name="Websocket_Port_min", type="integer")
     */
    private $websocketPortMin;

    /**
     * @var int
     *
     * @ORM\Column(name="Websocket_Port_max", type="integer")
     */
    private $websocketPortMax;

    /**
     * @var int
     *
     * @ORM\Column(name="Console_Port_min", type="integer")
     */
    private $consolePortMin;

    /**
     * @var int
     *
     * @ORM\Column(name="Console_Port_max", type="integer")
     */
    private $consolePortMax;
	
	/**
     * @var string
     *
     * @ORM\Column(name="IPv4", type="string", length=255,nullable=true)
     */
    private $ipv4;
	
	/**
     * @var string
     *
     * @ORM\Column(name="IPv6", type="string", length=255,nullable=true)
     */
    private $ipv6;

	
	/**
     * @var int
     * Index minimal à utiliser rapport au système. Exemple : nous avons déjà 13 interfaces de créer donc la prochaine interface_exists
	 * libre sera la tap14 par exemple.
     * @ORM\Column(name="index_min_interface", type="integer")
     */
    private $index_min_Interface;
	
	/**
     * @var int
     * Index actuel des interfaces de contrôle par rapport au système. Exemple : nous avons déjà 13 interfaces de créer donc la prochaine interface_exists
	 * libre sera la tap14 par exemple.
     * @ORM\Column(name="index_interface_controle", type="integer")
     */
    private $indexInterfaceControle;
	
	/**
     * @var int
     * Index actuel des interfaces classique par rapport au système. Exemple : nous avons déjà 13 interfaces de créer donc la prochaine interface_exists
	 * libre sera la tap14 par exemple.
     * @ORM\Column(name="index_interface", type="integer")
     */
    private $indexInterface;

	/**
     * @var int
     * Index of the network used
     * @ORM\Column(name="index_networkused", type="integer")
     */
    private $indexNetwork;
	
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
     * Set vNCPortMin
     *
     * @param integer $vNCPortMin
     *
     * @return Param_System
     */
    public function setVNCPortMin($vNCPortMin)
    {
        $this->VNCPortMin = $vNCPortMin;

        return $this;
    }

    /**
     * Get vNCPortMin
     *
     * @return integer
     */
    public function getVNCPortMin()
    {
        return $this->VNCPortMin;
    }

    /**
     * Set vNCPortMax
     *
     * @param integer $vNCPortMax
     *
     * @return Param_System
     */
    public function setVNCPortMax($vNCPortMax)
    {
        $this->VNCPortMax = $vNCPortMax;

        return $this;
    }

    /**
     * Get vNCPortMax
     *
     * @return integer
     */
    public function getVNCPortMax()
    {
        return $this->VNCPortMax;
    }

    /**
     * Set websocketPortMin
     *
     * @param integer $websocketPortMin
     *
     * @return Param_System
     */
    public function setWebsocketPortMin($websocketPortMin)
    {
        $this->websocketPortMin = $websocketPortMin;

        return $this;
    }

    /**
     * Get websocketPortMin
     *
     * @return integer
     */
    public function getWebsocketPortMin()
    {
        return $this->websocketPortMin;
    }

    /**
     * Set websocketPortMax
     *
     * @param integer $websocketPortMax
     *
     * @return Param_System
     */
    public function setWebsocketPortMax($websocketPortMax)
    {
        $this->websocketPortMax = $websocketPortMax;

        return $this;
    }

    /**
     * Get websocketPortMax
     *
     * @return integer
     */
    public function getWebsocketPortMax()
    {
        return $this->websocketPortMax;
    }

    /**
     * Set consolePortMin
     *
     * @param integer $consolePortMin
     *
     * @return Param_System
     */
    public function setConsolePortMin($consolePortMin)
    {
        $this->consolePortMin = $consolePortMin;

        return $this;
    }

    /**
     * Get consolePortMin
     *
     * @return integer
     */
    public function getConsolePortMin()
    {
        return $this->consolePortMin;
    }

    /**
     * Set consolePortMax
     *
     * @param integer $consolePortMax
     *
     * @return Param_System
     */
    public function setConsolePortMax($consolePortMax)
    {
        $this->consolePortMax = $consolePortMax;

        return $this;
    }

    /**
     * Get consolePortMax
     *
     * @return integer
     */
    public function getConsolePortMax()
    {
        return $this->consolePortMax;
    }

    /**
     * Set ipv4
     *
     * @param string $ipv4
     *
     * @return Param_System
     */
    public function setIpv4($ipv4)
    {
        $this->ipv4 = $ipv4;

        return $this;
    }

    /**
     * Get ipv4
     *
     * @return string
     */
    public function getIpv4()
    {
        return $this->ipv4;
    }

    /**
     * Set ipv6
     *
     * @param string $ipv6
     *
     * @return Param_System
     */
    public function setIpv6($ipv6)
    {
        $this->ipv6 = $ipv6;

        return $this;
    }

    /**
     * Get ipv6
     *
     * @return string
     */
    public function getIpv6()
    {
        return $this->ipv6;
    }

    /**
     * Set indexMinInterface
     *
     * @param integer $indexMinInterface
     *
     * @return Param_System
     */
    public function setIndexMinInterface($indexMinInterface)
    {
        $this->index_min_Interface = $indexMinInterface;

        return $this;
    }

    /**
     * Get indexMinInterface
     *
     * @return integer
     */
    public function getIndexMinInterface()
    {
        return $this->index_min_Interface;
    }

    /**
     * Set indexInterfaceControle
     *
     * @param integer $indexInterfaceControle
     *
     * @return Param_System
     */
    public function setIndexInterfaceControle($indexInterfaceControle)
    {
        $this->indexInterfaceControle = $indexInterfaceControle;

        return $this;
    }

    /**
     * Get indexInterfaceControle
     *
     * @return integer
     */
    public function getIndexInterfaceControle()
    {
        return $this->indexInterfaceControle;
    }

    /**
     * Set indexInterface
     *
     * @param integer $indexInterface
     *
     * @return Param_System
     */
    public function setIndexInterface($indexInterface)
    {
        $this->indexInterface = $indexInterface;

        return $this;
    }

    /**
     * Get indexInterface
     *
     * @return integer
     */
    public function getIndexInterface()
    {
        return $this->indexInterface;
    }

    /**
     * Set indexNetwork
     *
     * @param integer $indexNetwork
     *
     * @return Param_System
     */
    public function setIndexNetwork($indexNetwork)
    {
        $this->indexNetwork = $indexNetwork;

        return $this;
    }

    /**
     * Get indexNetwork
     *
     * @return integer
     */
    public function getIndexNetwork()
    {
        return $this->indexNetwork;
    }
}
