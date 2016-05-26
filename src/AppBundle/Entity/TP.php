<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
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

   

    private $file;

    /**
     * @ORM\Column(name="alt", type="string", length=255)
     */
    private $alt;



    /**
     * @ORM\Column(name="url", type="string", length=255)
     */
    private $url;

    /**
     * @ORM\Column(name="nom_fichier", type="string", length=255)
     */
    private $nom;
    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\LAB", mappedBy="tp")
     */
    private $labs;
    // On ajoute cet attribut pour y stocker le nom du fichier temporairement
    private $tempFilename;

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
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }


    //ces deux evenement permetent de remplir l'attribut url et alt avant l'enregestrement

     /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function preUpload()
    {
        // Si jamais il n'y a pas de fichier (champ facultatif)
        if (null === $this->file) {
            return;
        }

        // Le nom du fichier est son id, on doit juste stocker également son extension
        // Pour faire propre, on devrait renommer cet attribut en « extension », plutôt que « url »
        $this->url = $this->file->guessExtension();

        // Et on génère l'attribut alt de la pour le twig , à la valeur du nom du fichier sur le PC de l'internaute
        $this->alt = $this->file->getClientOriginalName();
    }

    /* En cas d'échec de l'enregistrement de l'entité en base de données, il ne faudrait
    pas se retrouver avec un fichier orphelin sur notre
    disque. On attend donc que l'enregistrement
    se fasse effectivement avant de déplacer le fichier. on utilise alors les deux event*/

    /**
     * @ORM\PostPersist()
     * @ORM\PostUpdate()
     */
    public function upload()
    {
        // Si jamais il n'y a pas de fichier (champ facultatif)
        if (null === $this->file) {
            return;
        }

        // Si on avait un ancien fichier, on le supprime
        if (null !== $this->tempFilename) {
            $oldFile = $this->getUploadRootDir().'/'.$this->id.'.'.$this->tempFilename;
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        // On déplace le fichier envoyé dans le répertoire de notre choix
        $this->file->move(
            $this->getUploadRootDir(), // Le répertoire de destination
            $this->id.'.'.$this->url   // Le nom du fichier à créer, ici « id.extension »
        );
    }




    public function setFile(UploadedFile $file = null )
    {
        $this->file = $file;
        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->url) {
            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->url;

            // On réinitialise les valeurs des attributs url et alt
            $this->url = null;
            $this->alt = null;
        }
    }


    public function getUploadDir()
    {
        // On retourne le chemin relatif vers l'image pour un navigateur
        return 'uploads/tps';
    }

    protected function getUploadRootDir()
    {
        // On retourne le chemin relatif vers l'image pour notre code PHP
        return __DIR__.'/../../../../web/'.$this->getUploadDir();
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
     * Constructor
     */
    public function __construct()
    {
        $this->labs = new \Doctrine\Common\Collections\ArrayCollection();

    }

    /**
     * Add lab
     *
     * @param \AppBundle\Entity\LAB $lab
     *
     * @return TP
     */
    public function addLab(\AppBundle\Entity\LAB $lab)
    {
        $this->labs[] = $lab;
        $lab->setTp($this);

        return $this;
    }

    /**
     * Remove lab
     *
     * @param \AppBundle\Entity\LAB $lab
     */
    public function removeLab(\AppBundle\Entity\LAB $lab)
    {
        $this->labs->removeElement($lab);
    }

    /**
     * Get labs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getLabs()
    {
        return $this->labs;
    }

   


    /**
     * Set alt
     *
     * @param string $alt
     *
     * @return TP
     */
    public function setAlt($alt)
    {
        $this->alt = $alt;

        return $this;
    }

    /**
     * Get alt
     *
     * @return string
     */
    public function getAlt()
    {
        return $this->alt;
    }
}
