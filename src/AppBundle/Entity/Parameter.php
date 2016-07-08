<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Parameter
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ParameterRepository")
 */
class Parameter
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
     * @ORM\Column(name="nom", type="string", length=255)
     */
    private $nom;
	
    /**
     * @var float
     *
     * @ORM\Column(name="size_memoire", type="float", length=40)
     */
    private $size_memoire;

    /**
     * @var float
     *
     * @ORM\Column(name="size_disque", type="float", length=40)
     */

    private $size_disque;

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
     * Set seizeMemoire
     *
     * @param float $seizeMemoire
     *
     * @return Parameter
     */
    public function setSizeMemoire($sizeMemoire)
    {
        $this->size_memoire = $sizeMemoire;

        return $this;
    }

    /**
     * Get seizeMemoire
     *
     * @return float
     */
    public function getSizeMemoire()
    {
        return $this->size_memoire;
    }

    /**
     * Set seizeDisque
     *
     * @param float $seizeDisque
     *
     * @return Parameter
     */
    public function setSizeDisque($sizeDisque)
    {
        $this->size_disque = $sizeDisque;

        return $this;
    }

    /**
     * Get seizeDisque
     *
     * @return float
     */
    public function getSizeDisque()
    {
        return $this->size_disque;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return Parameter
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
}
