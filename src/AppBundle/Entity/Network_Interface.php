<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Network_Interface
 * 
 * @ORM\Table(name="network_interface")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\Network_InterfaceRepository")
 */

class Network_Interface
{
    /**
     * @var integer
	 * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
     
    private $id;

    /**
     * @var string
     *
	 * @ORM\Column(name="Nom", type="string", length=255)
     */
    private $nom;


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
     * Set nom
     *
     * @param string $nom
     *
     * @return Interfaces
     */
    public function setNom($nom)
    {
        $this->Nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom()
    {
        return $this->Nom;
    }
}
