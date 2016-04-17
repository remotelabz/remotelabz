<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Device
 *
 * @ORM\Table(name="device")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DeviceRepository")
 */
class Device
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
     * @ORM\Column(name="Nom", type="string", length=255, unique=true)
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="Type", type="string", length=255)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="Modele", type="string", length=255)
     */
    private $modele;

    /**
     * @var string
     *
     * @ORM\Column(name="Version", type="string", length=255)
     */
    private $version;

    /**
     * @var string
     *
     * @ORM\Column(name="Marque", type="string", length=255)
     */
    private $marque;

	/**
	 *
	 * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Systeme")
	 * @ORM\JoinColumn(nullable=false)
     */
    private $Systeme;
	
	/**
	 *
	 * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
	 * @ORM\JoinColumn(nullable=false)
     */
    private $Network_Interfaces;
	
    /**
     *
	 * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
     */
    private $InterfControl;

	/**
	 *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Connexion")
     */
    private $Connexion;
	
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
     * Set nom
     *
     * @param string $nom
     *
     * @return Device
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return Device
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set modele
     *
     * @param string $modele
     *
     * @return Device
     */
    public function setModele($modele)
    {
        $this->modele = $modele;

        return $this;
    }

    /**
     * Get modele
     *
     * @return string
     */
    public function getModele()
    {
        return $this->modele;
    }

    /**
     * Set version
     *
     * @param string $version
     *
     * @return Device
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set marque
     *
     * @param string $marque
     *
     * @return Device
     */
    public function setMarque($marque)
    {
        $this->marque = $marque;

        return $this;
    }

    /**
     * Get marque
     *
     * @return string
     */
    public function getMarque()
    {
        return $this->marque;
    }

    /**
     * Set interfControl
     *
     * @param \stdClass $interfControl
     *
     * @return Device
     */
    public function setInterfControl($interfControl)
    {
        $this->interfControl = $interfControl;

        return $this;
    }

    /**
     * Get interfControl
     *
     * @return \stdClass
     */
    public function getInterfControl()
    {
        return $this->interfControl;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->Systeme = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add systeme
     *
     * @param \AppBundle\Entity\Systeme $systeme
     *
     * @return Device
     */
    public function addSysteme(\AppBundle\Entity\Systeme $systeme)
    {
        $this->Systeme[] = $systeme;

        return $this;
    }

    /**
     * Remove systeme
     *
     * @param \AppBundle\Entity\Systeme $systeme
     */
    public function removeSysteme(\AppBundle\Entity\Systeme $systeme)
    {
        $this->Systeme->removeElement($systeme);
    }

    /**
     * Get systeme
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSysteme()
    {
        return $this->Systeme;
    }

    /**
     * Set networkInterfaces
     *
     * @param \AppBundle\Entity\Network_Interface $networkInterfaces
     *
     * @return Device
     */
    public function setNetworkInterfaces(\AppBundle\Entity\Network_Interface $networkInterfaces)
    {
        $this->Network_Interfaces = $networkInterfaces;

        return $this;
    }

    /**
     * Get networkInterfaces
     *
     * @return \AppBundle\Entity\Network_Interface
     */
    public function getNetworkInterfaces()
    {
        return $this->Network_Interfaces;
    }

    /**
     * Set connexion
     *
     * @param \AppBundle\Entity\Connexion $connexion
     *
     * @return Device
     */
    public function setConnexion(\AppBundle\Entity\Connexion $connexion = null)
    {
        $this->Connexion = $connexion;

        return $this;
    }

    /**
     * Get connexion
     *
     * @return \AppBundle\Entity\Connexion
     */
    public function getConnexion()
    {
        return $this->Connexion;
    }
}
