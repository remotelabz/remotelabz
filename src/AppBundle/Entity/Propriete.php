<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Propriete
 *
 * Permet de définir les propriétés relatives à une reservation et surtout les ports utilisées pour accéder à un device virtuel
 * Chaque device est instantié au fur et à mesure des démarrages et donc pour éviter de dupliquer les devices, nous passons pas cet objet
 *
 * @ORM\Table(name="propriete")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProprieteRepository")
 */
class Propriete
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
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Device" )
     */
    private $device;

    /**
     * @var int
     *
     * @ORM\Column(name="proc_id", type="integer")
     */
    private $procId;

    /**
     * @var \stdClass
     *
     @ORM\OneToOne(targetEntity="AppBundle\Entity\ConfigReseau" )
     */
    private $configReseau;


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
     * Set device
     *
     * @param \stdClass $device
     *
     * @return Prorpriete
     */
    public function setDevice($device)
    {
        $this->device = $device;

        return $this;
    }

    /**
     * Get device
     *
     * @return \stdClass
     */
    public function getDevice()
    {
        return $this->device;
    }

    /**
     * Set procId
     *
     * @param integer $procId
     *
     * @return Prorpriete
     */
    public function setProcId($procId)
    {
        $this->procId = $procId;

        return $this;
    }

    /**
     * Get procId
     *
     * @return int
     */
    public function getProcId()
    {
        return $this->procId;
    }

    /**
     * Set configReseau
     *
     * @param \stdClass $configReseau
     *
     * @return Prorpriete
     */
    public function setConfigReseau($configReseau)
    {
        $this->configReseau = $configReseau;

        return $this;
    }

    /**
     * Get configReseau
     *
     * @return \stdClass
     */
    public function getConfigReseau()
    {
        return $this->configReseau;
    }
}
