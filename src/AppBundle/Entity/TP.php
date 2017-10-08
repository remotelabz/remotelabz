<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * TP
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TPRepository")
 * @ORM\HasLifecycleCallbacks
 */
class TP
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
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\LAB")
     */
    private $lab;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     */
    private  $nom;

    /**
     * @ORM\Column(type="string")
     * @Assert\File(maxSize="6000000")
     */
    private $file;

    /**
	 * Type peut être individuel ou groupe
	 * Individuel : peut être exécuté pour une seule personne et seule cette personne y aura accès
	 * Groupe : un groupe d'utilisateur aura accès au même TP. Cas de VM pré-configuré avec des IP et usage du VPN
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     */
    private  $type;

	/**
	* The access type to the lab : vpn or web
	* @ORM\Column(type="string", length=16)
	* @Assert\NotBlank
	*/	
	private $access;
	
	/**
	 * @ORM\OneToOne(targetEntity="AppBundle\Entity\NetworkUsed" )
	*/
	private $network_used;
	
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
     * @return TP
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
     * Set lab
     *
     * @param \AppBundle\Entity\LAB $lab
     *
     * @return TP
     */
    public function setLab(\AppBundle\Entity\LAB $lab = null)
    {
        $this->lab = $lab;

        return $this;
    }

    /**
     * Get lab
     *
     * @return \AppBundle\Entity\LAB
     */
    public function getLab()
    {
        return $this->lab;
    }

    /**
     * Set file
     *
     * @param string $file
     *
     * @return TP
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get file
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return TP
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set access
     *
     * @param string $access
     *
     * @return TP
     */
    public function setAccess($access)
    {
        $this->access = $access;

        return $this;
    }

    /**
     * Get access
     *
     * @return string
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * Set networkUsed
     *
     * @param \AppBundle\Entity\NetworkUsed $networkUsed
     *
     * @return TP
     */
    public function setNetworkUsed(\AppBundle\Entity\NetworkUsed $networkUsed = null)
    {
        $this->network_used = $networkUsed;

        return $this;
    }

    /**
     * Get networkUsed
     *
     * @return \AppBundle\Entity\NetworkUsed
     */
    public function getNetworkUsed()
    {
        return $this->network_used;
    }
}
