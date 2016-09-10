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
     * @var int
     * Chaque device possède des interfaces qui sont dupliquées à chaque instantiation. (exemple : les tap sont uniques par VM)
	 * Il faut donc savoir si la réservation utilise la tap10 ou la tap23 par exemple. Dans l'interface enregistrée lors de la création
	 * du device dans le système, on définit juste les interfaces dans l'ordre classique
     * @ORM\Column(name="index_deb_interface", type="integer")
     */
    private $index_deb_interface;


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

    /**
     * Set indexDebInterface
     *
     * @param integer $indexDebInterface
     *
     * @return Propriete
     */
    public function setIndexDebInterface($indexDebInterface)
    {
        $this->index_deb_interface = $indexDebInterface;

        return $this;
    }

    /**
     * Get indexDebInterface
     *
     * @return integer
     */
    public function getIndexDebInterface()
    {
        return $this->index_deb_interface;
    }
}
