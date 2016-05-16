<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LAB
 *
 * @ORM\Table(name="lab")
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
     *  @ORM\OneToMany(targetEntity="AppBundle\Entity\POD", mappedBy="lab")
     */
    private $pod;
    /**
     *  @ORM\OneToMany(targetEntity="AppBundle\Entity\Connexion", mappedBy="lab")
     */
    private $connexions;

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
     * Constructor
     */
    public function __construct()
    {
        $this->pod = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add pod
     *
     * @param \AppBundle\Entity\POD $pod
     *
     * @return LAB
     */
    public function addPod(\AppBundle\Entity\POD $pod)
    {
        $this->pod[] = $pod;
        $pod->setLab($this);

        return $this;
    }

    /**
     * Remove pod
     *
     * @param \AppBundle\Entity\POD $pod
     */
    public function removePod(\AppBundle\Entity\POD $pod)
    {
        $this->pod->removeElement($pod);
    }

    /**
     * Get pod
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPod()
    {
        return $this->pod;
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
     * Add connexion
     *
     * @param \AppBundle\Entity\Connexion $connexion
     *
     * @return LAB
     */
    public function addConnexion(\AppBundle\Entity\Connexion $connexion)
    {
        $this->connexions[] = $connexion;
        $connexion->setLab($this);

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
