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
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Device" )
     */
    private $device;

    /**
     * @var int
     *
     * @ORM\Column(name="proc_id", type="integer")
     */
    private $procId;

	/**
     * @var string
     *
     * @ORM\Column(name="br_num", type="string", length=255)
     */
    private $br_num;

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
     * Set procId
     *
     * @param integer $procId
     *
     * @return Run
     */
    public function setProcId($procId)
    {
        $this->procId = $procId;

        return $this;
    }

    /**
     * Get procId
     *
     * @return integer
     */
    public function getProcId()
    {
        return $this->procId;
    }

    /**
     * Set brNum
     *
     * @param string $brNum
     *
     * @return Run
     */
    public function setBrNum($brNum)
    {
        $this->br_num = $brNum;

        return $this;
    }

    /**
     * Get brNum
     *
     * @return string
     */
    public function getBrNum()
    {
        return $this->br_num;
    }

    /**
     * Set device
     *
     * @param \AppBundle\Entity\Device $device
     *
     * @return Run
     */
    public function setDevice(\AppBundle\Entity\Device $device = null)
    {
        $this->device = $device;

        return $this;
    }

    /**
     * Get device
     *
     * @return \AppBundle\Entity\Device
     */
    public function getDevice()
    {
        return $this->device;
    }
}
