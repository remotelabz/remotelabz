<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Connexion
 *
 * @ORM\Table(name="connexion")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ConnexionRepository")
 */
class Connexion
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;


    //je veux pas des connexions sans interface ni sans device JoinColumn(nullable=false)
    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Device")
     *  * @ORM\JoinColumn(nullable=false)
     */



    private $Device1;

    /**
     * @var \stdClass
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Device")
     * @ORM\JoinColumn(nullable=false)
     */
    private $Device2;

    /**
     *
	 * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
     * @ORM\JoinColumn(nullable=false)
     */
    private $interface1;

    /**
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
     * @ORM\JoinColumn(nullable=false)
     */

    private $interface2;

    /**
     * @var string
     *
     * @ORM\Column(name="NomeDevice1", type="string", length=255)
     */
    private $nomdevice1;


    /**
     * @var string
     *
     * @ORM\Column(name="NomDevice2", type="string", length=255)
     */
    private $nomdevice2;
    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\LAB", inversedBy="connexions")
     */
    private $lab;





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
     * Set interface1
     *
     * @param \stdClass $interface1
     *
     * @return Connexion
     */
    public function setInterface1($interface1)
    {
        $this->interface1 = $interface1;

        return $this;
    }

    /**
     * Get interface1
     *
     * @return \stdClass
     */
    public function getInterface1()
    {
        return $this->interface1;
    }

    /**
     * Set interface2
     *
     * @param string $interface2
     *
     * @return Connexion
     */
    public function setInterface2($interface2)
    {
        $this->interface2 = $interface2;

        return $this;
    }

    /**
     * Get interface2
     *
     * @return string
     */
    public function getInterface2()
    {
        return $this->interface2;
    }

    /**
     * Set device1
     *
     * @param \AppBundle\Entity\Device $device1
     *
     * @return Connexion
     */
    public function setDevice1(\AppBundle\Entity\Device $device1 = null)
    {
        $this->Device1 = $device1;

        return $this;
    }

    /**
     * Get device1
     *
     * @return \AppBundle\Entity\Device
     */
    public function getDevice1()
    {
        return $this->Device1;
    }

    /**
     * Set device2
     *
     * @param \AppBundle\Entity\Device $device2
     *
     * @return Connexion
     */
    public function setDevice2(\AppBundle\Entity\Device $device2 = null)
    {
        $this->Device2 = $device2;

        return $this;
    }

    /**
     * Get device2
     *
     * @return \AppBundle\Entity\Device
     */
    public function getDevice2()
    {
        return $this->Device2;
    }

    /**
     * Set nomdevice1
     *
     * @param string $nomdevice1
     *
     * @return Connexion
     */
    public function setNomdevice1($nomdevice1)
    {
        $this->nomdevice1 = $nomdevice1;

        return $this;
    }

    /**
     * Get nomdevice1
     *
     * @return string
     */
    public function getNomdevice1()
    {
        return $this->nomdevice1;
    }

    /**
     * Set nomdevice2
     *
     * @param string $nomdevice2
     *
     * @return Connexion
     */
    public function setNomdevice2($nomdevice2)
    {
        $this->nomdevice2 = $nomdevice2;

        return $this;
    }

    /**
     * Get nomdevice2
     *
     * @return string
     */
    public function getNomdevice2()
    {
        return $this->nomdevice2;
    }

    /**
     * Set lab
     *
     * @param \AppBundle\Entity\LAB $lab
     *
     * @return Connexion
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
}
