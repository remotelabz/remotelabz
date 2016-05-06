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
	 * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Hyperviseur",cascade={"persist"})
	 * @ORM\JoinColumn(nullable=false)
     */
    private $hyperviseur;

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
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Parameter",cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $parametres;

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
     * Set pathMaster
     *
     * @param string $pathMaster
     *
     * @return Systeme
     */
    public function setPathMaster($pathMaster)
    {
        $this->path_master = $pathMaster;

        return $this;
    }

    /**
     * Get pathMaster
     *
     * @return string
     */
    public function getPathMaster()
    {
        return $this->path_master;
    }

    /**
     * Set pathRelatif
     *
     * @param string $pathRelatif
     *
     * @return Systeme
     */
    public function setPathRelatif($pathRelatif)
    {
        $this->path_relatif = $pathRelatif;

        return $this;
    }

    /**
     * Get pathRelatif
     *
     * @return string
     */
    public function getPathRelatif()
    {
        return $this->path_relatif;
    }

    /**
     * Set parametres
     *
     * @param \AppBundle\Entity\Parameter $parametres
     *
     * @return Systeme
     */
    public function setParametres(\AppBundle\Entity\Parameter $parametres)
    {
        $this->parametres = $parametres;

        return $this;
    }

    /**
     * Get parametres
     *
     * @return \AppBundle\Entity\Parameter
     */
    public function getParametres()
    {
        return $this->parametres;
    }
}
