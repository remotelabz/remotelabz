<?php

namespace UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Classe
 *
 * @ORM\Table(name="classe")
 * @ORM\Entity(repositoryClass="UserBundle\Repository\ClasseRepository")
 */
class Classe
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
     * @ORM\ManyToMany(targetEntity="UserBundle\Entity\User", mappedBy="classes")
     *
     */
    private $users;
	
	/**
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\TP", inversedBy="classes")
     *
     */
    private $tps;
	

   
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
        $this->tps = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set nom
     *
     * @param string $nom
     *
     * @return Classe
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
     * Add user
     *
     * @param \UserBundle\Entity\User $user
     *
     * @return Classe
     */
    public function addUser(\UserBundle\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \UserBundle\Entity\User $user
     */
    public function removeUser(\UserBundle\Entity\User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Add tp
     *
     * @param \AppBundle\Entity\TP $tp
     *
     * @return Classe
     */
    public function addTp(\AppBundle\Entity\TP $tp)
    {
        $this->tps[] = $tp;

        return $this;
    }

    /**
     * Remove tp
     *
     * @param \AppBundle\Entity\TP $tp
     */
    public function removeTp(\AppBundle\Entity\TP $tp)
    {
        $this->tps->removeElement($tp);
    }

    /**
     * Get tps
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTps()
    {
        return $this->tps;
    }
}
