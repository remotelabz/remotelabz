<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LAB
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\LABRepository")
 */
class LAB
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
     * @ORM\Column(name="Nomlab", type="string", length=255)
     */
    private $nomlab;

    /**
     *  @ORM\ManyToOne(targetEntity="AppBundle\Entity\POD")
     */
    private $pod;

    /**
     *  @ORM\ManyToMany(targetEntity="AppBundle\Entity\Connexion", mappedBy="labs")
     */
    private $connexions;
	
	public function __construct() {
        $this->connexions = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set nomlab
     *
     * @param string $nomlab
     *
     * @return LAB
     */
    public function setNomlab($nomlab)
    {
        $this->nomlab = $nomlab;

        return $this;
    }

    /**
     * Get nomlab
     *
     * @return string
     */
    public function getNomlab()
    {
        return $this->nomlab;
    }

    /**
     * Set pod
     *
     * @param \AppBundle\Entity\POD $pod
     *
     * @return LAB
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
     * Add connexion
     *
     * @param \AppBundle\Entity\Connexion $connexion
     *
     * @return LAB
     */
    public function addConnexion(\AppBundle\Entity\Connexion $connexion)
    {
        $this->connexions[] = $connexion;

        return $this;
    }

    /**
     * Remove connexion
     *
     * @param \AppBundle\Entity\Connexion $connexion
     */
    public function removeConnexion(\AppBundle\Entity\Connexion $connexion)
    {
        $this->connexions->removeElement($connexion);
    }

    /**
     * Get connexions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getConnexions()
    {
        return $this->connexions;
    }
}
