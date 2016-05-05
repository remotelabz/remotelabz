<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Network_Interface
 *
 * @ORM\Table(name="network__interface")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\Network_InterfaceRepository")
 */
class Network_Interface
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
     * @ORM\Column(name="nom_interface", type="string", length=255)
     */
    private $nomInterface;

    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\ConfigReseau")
     */

    private $config_reseau;


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
     * Set nomInterface
     *
     * @param string $nomInterface
     *
     * @return Network_Interface
     */
    public function setNomInterface($nomInterface)
    {
        $this->nomInterface = $nomInterface;

        return $this;
    }

    /**
     * Get nomInterface
     *
     * @return string
     */
    public function getNomInterface()
    {
        return $this->nomInterface;
    }

    /**
     * Set configReseau
     *
     * @param \AppBundle\Entity\ConfigReseau $configReseau
     *
     * @return Network_Interface
     */
    public function setConfigReseau(\AppBundle\Entity\ConfigReseau $configReseau = null)
    {
        $this->config_reseau = $configReseau;

        return $this;
    }

    /**
     * Get configReseau
     *
     * @return \AppBundle\Entity\ConfigReseau
     */
    public function getConfigReseau()
    {
        return $this->config_reseau;
    }
}
