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
     * @ORM\Column(name="NomPod", type="string", length=255)
     */
    private $nomPod;

    /**
     *  @ORM\OneToMany(targetEntity="AppBundle\Entity\POD", mappedBy="lab")
     */
    private $pod;

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
     * Set nomPod
     *
     * @param string $nomPod
     *
     * @return LAB
     */
    public function setNomPod($nomPod)
    {
        $this->nomPod = $nomPod;

        return $this;
    }

    /**
     * Get nomPod
     *
     * @return string
     */
    public function getNomPod()
    {
        return $this->nomPod;
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
        $pod->setLab(this);

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
}
