<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;



/**
 * Network_Interface
 * @ORM\Entity(repositoryClass="AppBundle\Repository\Network_InterfaceRepository")
 */

class Network_Interface
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
     * @ORM\Column(name="nom_virtuel_interface", type="string", length=255)
     *  @Assert\NotBlank()
     */
    private $nom_virtuel;
	
	/**
     * @var string
     * @ORM\Column(name="nom_physique_interface", type="string", length=255)
     *  @Assert\NotBlank()
     */
    private $nom_physique;


    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\ConfigReseau", cascade={"persist","remove"})
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $config_reseau;
	
    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Device",inversedBy="network_interfaces")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    private $device;
	
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
     * Set configReseau
     *
     * @param \AppBundle\Entity\ConfigReseau $configReseau
     *
     * @return Network_Interface
     */
    public function setConfigReseau(\AppBundle\Entity\ConfigReseau $configReseau = null)
    {
        $this->config_reseau = $configReseau;

        return $this;
    }

    /**
     * Get configReseau
     *
     * @return \AppBundle\Entity\ConfigReseau
     */
    public function getConfigReseau()
    {
        return $this->config_reseau;
    }

    /**
     * Set device
     *
     * @param \AppBundle\Entity\Device $device
     *
     * @return Network_Interface
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
//    public function setNbrInterface($nbrInterface)
//    {
//        $this->nbr_interface = $nbrInterface;
//
//        return $this;
//    }
//
// 
//    public function getNbrInterface()
//    {
//        return $this->nbr_interface;
//    }

    /**
     * Set nomVirtuel
     *
     * @param string $nomVirtuel
     *
     * @return Network_Interface
     */
    public function setNomVirtuel($nomVirtuel)
    {
        $this->nom_virtuel = $nomVirtuel;

        return $this;
    }

    /**
     * Get nomVirtuel
     *
     * @return string
     */
    public function getNomVirtuel()
    {
        return $this->nom_virtuel;
    }

    /**
     * Set nomPhysique
     *
     * @param string $nomPhysique
     *
     * @return Network_Interface
     */
    public function setNomPhysique($nomPhysique)
    {
        $this->nom_physique = $nomPhysique;

        return $this;
    }

    /**
     * Get nomPhysique
     *
     * @return string
     */
    public function getNomPhysique()
    {
        return $this->nom_physique;
    }
}
