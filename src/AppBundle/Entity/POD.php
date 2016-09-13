<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * POD
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PODRepository")
 * @ORM\Table(name="pod")
 
 */
class POD
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
     *  @ORM\OneToMany(targetEntity="AppBundle\Entity\Device", mappedBy="pod")
     */

    private $devices;

//    /**
//     * @var array
//     *
//     * @ORM\Column(name="NomDevice", type="array")
//     */
//    private $NomDevice;

    /**
     * @var string
     *
     * @ORM\Column(name="Nom_pod", type="string", length=255)
     */

    private $nom;




    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function __toString() {
        return $this->nom;
    }



    /**
     * Constructor
     */
    public function __construct()
    {
        $this->devices = new \Doctrine\Common\Collections\ArrayCollection();
//        $this->NomDevice = array();
    }

    /**
     * Add device
     *
     * @param \AppBundle\Entity\Device $device
     *
     * @return POD
     */
    public function addDevice(\AppBundle\Entity\Device $device)
    {
        $this->devices[] = $device;
        $device->setPod($this);
        return $this;
    }

    /**
     * Remove device
     *
     * @param \AppBundle\Entity\Device $device
     */
    public function removeDevice(\AppBundle\Entity\Device $device)
    {
        $this->devices->removeElement($device);
    }

    /**
     * Get devices
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getDevices()
    {
        return $this->devices;
    }


    /**
     * Set lab
     *
     * @param \AppBundle\Entity\LAB $lab
     *
     * @return POD
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
     * Set nom
     *
     * @param string $nom
     *
     * @return POD
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



//    /**
//     * Set nomDevice
//     *
//     * @param array $nomDevice
//     *
//     * @return POD
//     */
//    public function setNomDevice( array $nomDevice)
//    {
//        $this->NomDevice = $nomDevice;
//
//        return $this;
//    }

//    /**
//     * Get nomDevice
//     *
//     * @return array
//     */
//    public function getNomDevice()
//    {
//        return $this->NomDevice;
//    }
}
