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

    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Device")
     */
    private $Device1;

    /**
     * @var \stdClass
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Device")
     */
    private $Device2;

    /**
     *
	 * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
     */
    private $Interface1;

    /**
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Network_Interface")
     */
    private $Interface2;


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
     * Set nomDevice1
     *
     * @param \stdClass $nomDevice1
     *
     * @return Connexion
     */
    public function setNomDevice1($nomDevice1)
    {
        $this->nomDevice1 = $nomDevice1;

        return $this;
    }

    /**
     * Get nomDevice1
     *
     * @return \stdClass
     */
    public function getNomDevice1()
    {
        return $this->nomDevice1;
    }

    /**
     * Set nomDevice2
     *
     * @param \stdClass $nomDevice2
     *
     * @return Connexion
     */
    public function setNomDevice2($nomDevice2)
    {
        $this->nomDevice2 = $nomDevice2;

        return $this;
    }

    /**
     * Get nomDevice2
     *
     * @return \stdClass
     */
    public function getNomDevice2()
    {
        return $this->nomDevice2;
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
}
