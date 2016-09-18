<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Reservation
 * Reservation en cours et futur
 * @ORM\Table(name="reservation")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ReservationRepository")
 */
class Reservation
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
     *
	 * @ORM\ManyToOne(targetEntity="UserBundle\Entity\User" )
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="IP_src", type="string", length=255)
     */
    private $IPSrc;
	
    /**
     * @var \stdClass
     *
     * @ORM\Column(name="TP", type="object")
     */
    private $TP;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_debut", type="datetime")
     */
    private $DateDebut;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_fin", type="datetime")
     */
    private $DateFin;

    /**
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Run", mappedBy="reservation" )
     */
    private $Run;


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
     * Set user
     *
     * @param \stdClass $user
     *
     * @return Reservation
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \stdClass
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set iPSrc
     *
     * @param string $iPSrc
     *
     * @return Reservation
     */
    public function setIPSrc($iPSrc)
    {
        $this->iPSrc = $iPSrc;

        return $this;
    }

    /**
     * Get iPSrc
     *
     * @return string
     */
    public function getIPSrc()
    {
        return $this->iPSrc;
    }

    /**
     * Set tP
     *
     * @param \stdClass $tP
     *
     * @return Reservation
     */
    public function setTP($tP)
    {
        $this->tP = $tP;

        return $this;
    }

    /**
     * Get tP
     *
     * @return \stdClass
     */
    public function getTP()
    {
        return $this->tP;
    }

    /**
     * Set dateDebut
     *
     * @param \DateTime $dateDebut
     *
     * @return Reservation
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    /**
     * Get dateDebut
     *
     * @return \DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * Set dateFin
     *
     * @param \DateTime $dateFin
     *
     * @return Reservation
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Get dateFin
     *
     * @return \DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * Set propriete
     *
     * @param \stdClass $propriete
     *
     * @return Reservation
     */
    public function setPropriete($propriete)
    {
        $this->propriete = $propriete;

        return $this;
    }

    /**
     * Get propriete
     *
     * @return \stdClass
     */
    public function getPropriete()
    {
        return $this->propriete;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->Propriete = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add propriete
     *
     * @param \AppBundle\Entity\Propriete $propriete
     *
     * @return Reservation
     */
    public function addPropriete(\AppBundle\Entity\Propriete $propriete)
    {
        $this->Propriete[] = $propriete;

        return $this;
    }

    /**
     * Remove propriete
     *
     * @param \AppBundle\Entity\Propriete $propriete
     */
    public function removePropriete(\AppBundle\Entity\Propriete $propriete)
    {
        $this->Propriete->removeElement($propriete);
    }

    /**
     * Add run
     *
     * @param \AppBundle\Entity\Run $run
     *
     * @return Reservation
     */
    public function addRun(\AppBundle\Entity\Run $run)
    {
        $this->Run[] = $run;

        return $this;
    }

    /**
     * Remove run
     *
     * @param \AppBundle\Entity\Run $run
     */
    public function removeRun(\AppBundle\Entity\Run $run)
    {
        $this->Run->removeElement($run);
    }

    /**
     * Get run
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRun()
    {
        return $this->Run;
    }
}
