<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Run
 *
 * Permet de définir les propriétés relatives à une reservation et surtout les ports utilisées pour accéder à un device virtuel
 * Chaque device est instantié au fur et à mesure des démarrages et donc pour éviter de dupliquer les devices, nous passons par cet objet
 *
 * @ORM\Table(name="run")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\RunRepository")
 */
class Run
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
     * @var \stdClass
     *
     * @ORM\OneToOne(targetEntity="UserBundle\Entity\User" )
     */
    private $user;
	
	/**
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\TP" )
     */
    private $tp;
	
	/**
     * @var string
     * Nom de l'instance du TP sur le système de virtualisation afin de retrouver le répertoire dans lequel est stocké les images et les scripts de lancement et d'arrêt des VM
	 * @ORM\Column(name="tp_process_name", type="string", length=255)
     */
    private $tp_process_name;
	
	/**
     * @var string
     * Répertoire dans lequel est stocké les configurations xml des TP et les scripts de lancement, arrêt, ...
	 * @ORM\Column(name="dir_tp_user", type="string", length=255)
     */
    private $dir_tp_user; 



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
     * Set tpProcessName
     *
     * @param string $tpProcessName
     *
     * @return Run
     */
    public function setTpProcessName($tpProcessName)
    {
        $this->tp_process_name = $tpProcessName;

        return $this;
    }

    /**
     * Get tpProcessName
     *
     * @return string
     */
    public function getTpProcessName()
    {
        return $this->tp_process_name;
    }

    /**
     * Set dirTpUser
     *
     * @param string $dirTpUser
     *
     * @return Run
     */
    public function setDirTpUser($dirTpUser)
    {
        $this->dir_tp_user = $dirTpUser;

        return $this;
    }

    /**
     * Get dirTpUser
     *
     * @return string
     */
    public function getDirTpUser()
    {
        return $this->dir_tp_user;
    }

    /**
     * Set user
     *
     * @param \UserBundle\Entity\User $user
     *
     * @return Run
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
     * Set tp
     *
     * @param \AppBundle\Entity\TP $tp
     *
     * @return Run
     */
    public function setTp(\AppBundle\Entity\TP $tp = null)
    {
        $this->tp = $tp;

        return $this;
    }

    /**
     * Get tp
     *
     * @return \AppBundle\Entity\TP
     */
    public function getTp()
    {
        return $this->tp;
    }
}
