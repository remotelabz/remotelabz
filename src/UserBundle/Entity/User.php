<?php

namespace UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;

/**
 * User
 *
 * @ORM\Table(name="user")
 * @ORM\Entity(repositoryClass="UserBundle\Repository\UserRepository")
 */
class User extends BaseUser
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=255)
     */
    protected $firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=255)
     */
    protected $lastname;


	/**
	 *
     * @ORM\ManyToOne(targetEntity="UserBundle\Entity\Groupe", cascade={"persist"})
     */
    protected $groupe;
	
	/**
     * @ORM\ManyToMany(targetEntity="UserBundle\Entity\Classe", inversedBy="users")
     *
     */
    private $classes;


	public function getLabel()
    {
        

        return $this->lastname." ".$this->firstname;
    }
	
    /**
     * Set firstname
     *
     * @param string $firstname
     *
     * @return User
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get firstname
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set lastname
     *
     * @param string $lastname
     *
     * @return User
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set groupe
     *
     * @param \UserBundle\Entity\Groupe $groupe
     *
     * @return User
     */
    public function setGroupe(\UserBundle\Entity\Groupe $groupe = null)
    {
        $this->groupe = $groupe;

        return $this;
    }

    /**
     * Get groupe
     *
     * @return \UserBundle\Entity\Groupe
     */
    public function getGroupe()
    {
        return $this->groupe;
    }

    /**
     * Add class
     *
     * @param \UserBundle\Entity\Classe $class
     *
     * @return User
     */
    public function addClasse(\UserBundle\Entity\Classe $class)
    {
        $this->classes[] = $class;

        return $this;
    }

    /**
     * Remove class
     *
     * @param \UserBundle\Entity\Classe $class
     */
    public function removeClasse(\UserBundle\Entity\Classe $class)
    {
        $this->classes->removeElement($class);
    }

    /**
     * Get classes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getClasses()
    {
        return $this->classes;
    }
}
