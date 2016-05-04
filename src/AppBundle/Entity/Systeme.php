<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Systeme
 *
 * @ORM\Table(name="systeme")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SystemeRepository")
 */
class Systeme
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
     * @ORM\Column(name="Nom", type="string", length=255)
     */
    private $nom;

    /**
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Hyperviseur")
	 * @ORM\JoinColumn(nullable=false)
     */
    private $Hyperviseur;

	/**
     * @var string
		*
     * @ORM\Column(name="path_master", type="string", length=255)
     */
    private $path_master;
	
	/**
     * @var string
		*
     * @ORM\Column(name="path_relatif", type="string", length=255)
     */
    private $path_relatif;

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
     * @return Systeme
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
     * Set hyperviseur
     *
     * @param \stdClass $hyperviseur
     *
     * @return Systeme
     */
    public function setHyperviseur($hyperviseur)
    {
        $this->hyperviseur = $hyperviseur;

        return $this;
    }

    /**
     * Get hyperviseur
     *
     * @return \stdClass
     */
    public function getHyperviseur()
    {
        return $this->hyperviseur;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->Hyperviseur = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add hyperviseur
     *
     * @param \AppBundle\Entity\Hyperviseur $hyperviseur
     *
     * @return Systeme
     */
    public function addHyperviseur(\AppBundle\Entity\Hyperviseur $hyperviseur)
    {
        $this->Hyperviseur[] = $hyperviseur;

        return $this;
    }

    /**
     * Remove hyperviseur
     *
     * @param \AppBundle\Entity\Hyperviseur $hyperviseur
     */
    public function removeHyperviseur(\AppBundle\Entity\Hyperviseur $hyperviseur)
    {
        $this->Hyperviseur->removeElement($hyperviseur);
    }
}
