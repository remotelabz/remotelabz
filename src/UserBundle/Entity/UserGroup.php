<?php

namespace UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserGroup
 *
 * @ORM\Table(name="usergroup")
 * @ORM\Entity(repositoryClass="UserBundle\Repository\UserGroupRepository")
 */
class UserGroup
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;
	
	/**
     *  @ORM\ManyToOne(targetEntity="UserBundle\Entity\User")
     */
    private $user;

	/**
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\TP", inversedBy="usergroups")
     *
     */
    private $tps;
	
    /**
     * Constructor
     */
    public function __construct()
    {
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
     * Set name
     *
     * @param string $name
     *
     * @return UserGroup
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set user
     *
     * @param \UserBundle\Entity\User $user
     *
     * @return UserGroup
     */
    public function setUser(\UserBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \UserBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add tp
     *
     * @param \AppBundle\Entity\TP $tp
     *
     * @return UserGroup
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
