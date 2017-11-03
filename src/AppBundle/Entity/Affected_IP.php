<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Affected_IP
 *
 * @ORM\Table(name="affected_ip")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\Affected_IPRepository")
 */
class Affected_IP
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
	* Réseau affecté à la maquette réseau
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\NetworkUsed" )
	*/
    private $idNetwork;

    /**
     * @var int
     *
     * @ORM\Column(name="index_ip", type="integer")
     */
    private $indexIp;

    /**
     * @var string
     *
     * @ORM\Column(name="tp_process_name", type="string", length=255)
     */
    private $tpProcessName;

    /**
     * @var \stdClass
     * Permet de savoir quelle IP est utilisée par un utilisateur pour son VPN
     * @ORM\OneToOne(targetEntity="UserBundle\Entity\User" )
     */
    private $user;

	/**
	 * @var \stdClass
     *  @ORM\ManyToOne(targetEntity="AppBundle\Entity\Device")
	 * Permet de savoir quelle IP est utilisée par un device
     */
    private $device;
	
 

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
     * Set indexIp
     *
     * @param integer $indexIp
     *
     * @return Affected_IP
     */
    public function setIndexIp($indexIp)
    {
        $this->indexIp = $indexIp;

        return $this;
    }

    /**
     * Get indexIp
     *
     * @return integer
     */
    public function getIndexIp()
    {
        return $this->indexIp;
    }

    /**
     * Set tpProcessName
     *
     * @param string $tpProcessName
     *
     * @return Affected_IP
     */
    public function setTpProcessName($tpProcessName)
    {
        $this->tpProcessName = $tpProcessName;

        return $this;
    }

    /**
     * Get tpProcessName
     *
     * @return string
     */
    public function getTpProcessName()
    {
        return $this->tpProcessName;
    }

    /**
     * Set idNetwork
     *
     * @param \AppBundle\Entity\NetworkUsed $idNetwork
     *
     * @return Affected_IP
     */
    public function setIdNetwork(\AppBundle\Entity\NetworkUsed $idNetwork = null)
    {
        $this->idNetwork = $idNetwork;

        return $this;
    }

    /**
     * Get idNetwork
     *
     * @return \AppBundle\Entity\NetworkUsed
     */
    public function getIdNetwork()
    {
        return $this->idNetwork;
    }

    /**
     * Set user
     *
     * @param \UserBundle\Entity\User $user
     *
     * @return Affected_IP
     */
    public function setUser(\UserBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \UserBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set device
     *
     * @param \AppBundle\Entity\Device $device
     *
     * @return Affected_IP
     */
    public function setDevice(\AppBundle\Entity\Device $device = null)
    {
        $this->device = $device;

        return $this;
    }

    /**
     * Get device
     *
     * @return \AppBundle\Entity\Device
     */
    public function getDevice()
    {
        return $this->device;
    }
}
