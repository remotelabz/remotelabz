<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Connexion
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ConnexionRepository")
 */
class Connexion
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
     * @ORM\Column(name="NomConnexion", type="string", length=255)
     */
    private $nomconnexion;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\POD")
     */
    private $pod;

    /**
     * @var string
     *
     * @ORM\Column(name="vlan1", type="integer")
     */
    private $vlan1;
    /**
     * @var string
     *
     * @ORM\Column(name="vlan2", type="integer")
     */
    private $vlan2;


    /**
     * @var \stdClass
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Device")
     * @ORM\joinColumn(onDelete="SET NULL",nullable=true)
     */
    private $Device1;

    /**
     * @var \stdClass
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Device")
     * @ORM\joinColumn(onDelete="SET NULL",nullable=true)
     */
    private $Device2;

    /**
     *
	 * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
     * @ORM\joinColumn(onDelete="SET NULL",nullable=true)
     */
    private $interface1;

    /**
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
     * @ORM\joinColumn(onDelete="SET NULL",nullable=true)
     */

    private $interface2;

    /**
     * @var string
     *
     * @ORM\Column(name="NomeDevice1", type="string", length=255)
     */
    private $nomdevice1;


    /**
     * @var string
     *
     * @ORM\Column(name="NomDevice2", type="string", length=255)
     */
    private $nomdevice2;

    /**
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\LAB", inversedBy="connexions")
     * @ORM\joinColumn(onDelete="SET NULL",nullable=true)
     */
    private $labs;
	
	public function __construct() {
        $this->labs = new \Doctrine\Common\Collections\ArrayCollection();
    }


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
     * Set nomconnexion
     *
     * @param string $nomconnexion
     *
     * @return Connexion
     */
    public function setNomconnexion($nomconnexion)
    {
        $this->nomconnexion = $nomconnexion;

        return $this;
    }

    /**
     * Get nomconnexion
     *
     * @return string
     */
    public function getNomconnexion()
    {
        return $this->nomconnexion;
    }

    /**
     * Set vlan1
     *
     * @param integer $vlan1
     *
     * @return Connexion
     */
    public function setVlan1($vlan1)
    {
        $this->vlan1 = $vlan1;

        return $this;
    }

    /**
     * Get vlan1
     *
     * @return integer
     */
    public function getVlan1()
    {
        return $this->vlan1;
    }

    /**
     * Set vlan2
     *
     * @param integer $vlan2
     *
     * @return Connexion
     */
    public function setVlan2($vlan2)
    {
        $this->vlan2 = $vlan2;

        return $this;
    }

    /**
     * Get vlan2
     *
     * @return integer
     */
    public function getVlan2()
    {
        return $this->vlan2;
    }

    /**
     * Set nomdevice1
     *
     * @param string $nomdevice1
     *
     * @return Connexion
     */
    public function setNomdevice1($nomdevice1)
    {
        $this->nomdevice1 = $nomdevice1;

        return $this;
    }

    /**
     * Get nomdevice1
     *
     * @return string
     */
    public function getNomdevice1()
    {
        return $this->nomdevice1;
    }

    /**
     * Set nomdevice2
     *
     * @param string $nomdevice2
     *
     * @return Connexion
     */
    public function setNomdevice2($nomdevice2)
    {
        $this->nomdevice2 = $nomdevice2;

        return $this;
    }

    /**
     * Get nomdevice2
     *
     * @return string
     */
    public function getNomdevice2()
    {
        return $this->nomdevice2;
    }

    /**
     * Set pod
     *
     * @param \AppBundle\Entity\POD $pod
     *
     * @return Connexion
     */
    public function setPod(\AppBundle\Entity\POD $pod = null)
    {
        $this->pod = $pod;

        return $this;
    }

    /**
     * Get pod
     *
     * @return \AppBundle\Entity\POD
     */
    public function getPod()
    {
        return $this->pod;
    }

    /**
     * Set device1
     *
     * @param \AppBundle\Entity\Device $device1
     *
     * @return Connexion
     */
    public function setDevice1(\AppBundle\Entity\Device $device1 = null)
    {
        $this->Device1 = $device1;

        return $this;
    }

    /**
     * Get device1
     *
     * @return \AppBundle\Entity\Device
     */
    public function getDevice1()
    {
        return $this->Device1;
    }

    /**
     * Set device2
     *
     * @param \AppBundle\Entity\Device $device2
     *
     * @return Connexion
     */
    public function setDevice2(\AppBundle\Entity\Device $device2 = null)
    {
        $this->Device2 = $device2;

        return $this;
    }

    /**
     * Get device2
     *
     * @return \AppBundle\Entity\Device
     */
    public function getDevice2()
    {
        return $this->Device2;
    }

    /**
     * Set interface1
     *
     * @param \AppBundle\Entity\Network_Interface $interface1
     *
     * @return Connexion
     */
    public function setInterface1(\AppBundle\Entity\Network_Interface $interface1 = null)
    {
        $this->interface1 = $interface1;

        return $this;
    }

    /**
     * Get interface1
     *
     * @return \AppBundle\Entity\Network_Interface
     */
    public function getInterface1()
    {
        return $this->interface1;
    }

    /**
     * Set interface2
     *
     * @param \AppBundle\Entity\Network_Interface $interface2
     *
     * @return Connexion
     */
    public function setInterface2(\AppBundle\Entity\Network_Interface $interface2 = null)
    {
        $this->interface2 = $interface2;

        return $this;
    }

    /**
     * Get interface2
     *
     * @return \AppBundle\Entity\Network_Interface
     */
    public function getInterface2()
    {
        return $this->interface2;
    }

    /**
     * Add lab
     *
     * @param \AppBundle\Entity\LAB $lab
     *
     * @return Connexion
     */
    public function addLab(\AppBundle\Entity\LAB $lab)
    {
        $this->labs[] = $lab;

        return $this;
    }

    /**
     * Remove lab
     *
     * @param \AppBundle\Entity\LAB $lab
     */
    public function removeLab(\AppBundle\Entity\LAB $lab)
    {
        $this->labs->removeElement($lab);
    }

    /**
     * Get labs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getLabs()
    {
        return $this->labs;
    }
}
