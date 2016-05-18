<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TP
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TPRepository")
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
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    private $file;
    /**
     * Get id
     *
     * @return int
     */
    /**
     * @ORM\Column(name="url", type="string", length=255)
     */
    private $url;
    /**
     * @ORM\Column(name="nom_fichier", type="string", length=255)
     */
    private $nom;
    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\TP",cascade={"persist"})
     *@ORM\JoinColumn(nullable=false)
     */
    private $lab;
    public function getId()
    {
        return $this->id;
    }


    public function setFile(UploadedFile $file = null )
    {
        $this->file = $file;
    }



    /**
     * Set url
     *
     * @param string $url
     *
     * @return TP
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
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
     * @param \AppBundle\Entity\TP $lab
     *
     * @return TP
     */
    public function setLab(\AppBundle\Entity\TP $lab)
    {
        $this->lab = $lab;

        return $this;
    }

    /**
     * Get lab
     *
     * @return \AppBundle\Entity\TP
     */
    public function getLab()
    {
        return $this->lab;
    }
}
