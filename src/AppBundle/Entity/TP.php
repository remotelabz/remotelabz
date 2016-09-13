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
     * @Assert\File(maxSize="6000000")
     */
    private $file;

    private $temp;


    //store the relative path to the file to use it in twig 

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public $path;
    
    public function getAbsolutePath()
    {
        return null === $this->path
            ? null
//            : $this->getUploadRootDir().'/'.$this->id.'.'.$this->path;
            : $this->getUploadRootDir().'/'.$this->id.'.'.$this->path;
    }

    public function getWebPath()
    {
        return null === $this->path
            ? null
            : $this->getUploadDir().'/';
    }


    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function preUpload()
    {
        if (null !== $this->getFile()) {
            $this->path = $this->getFile()->guessExtension();
        }
    }

    /**
     * @ORM\PostPersist()
     * @ORM\PostUpdate()
     */
    public function upload()
    {
        if (null === $this->getFile()) {
            return;
        }

        // check if we have an old image
        if (isset($this->temp)) {
            // delete the old image
            unlink($this->temp);
            // clear the temp image path
            $this->temp = null;
        }

        // you must throw an exception here if the file cannot be moved
        // so that the entity is not persisted to the database
        // which the UploadedFile move() method does
        $this->getFile()->move(
            $this->getUploadRootDir(),
            $this->id.'.'.$this->getFile()->guessExtension()
        );

        $this->setFile(null);


    }
    /**
     * @ORM\PreRemove()
     */
    public function storeFilenameForRemove()
    {
        $this->temp = $this->getAbsolutePath();
    }

    /**
     * @ORM\PostRemove()
     */
    public function removeUpload()
    {
        if (isset($this->temp)) {
            unlink($this->temp);
        }
    }




    protected function getUploadRootDir()
    {
        // the absolute directory path where uploaded
        // documents should be saved
        return __DIR__.'/../../../web/'.$this->getUploadDir();

    }
    protected function getUploadDir()
    {
        // get rid of the __DIR__ so it doesn't screw up
        // when displaying uploaded doc/image in the view.
        return 'uploads/documents';
    }


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
     * Sets file.
     *
     * @param UploadedFile $file
     */
    public function setFile(UploadedFile $file = null)
    {
        $this->file = $file;
        // check if we have an old image path
        if (is_file($this->getAbsolutePath())) {
            // store the old name to delete after the update
            $this->temp = $this->getAbsolutePath();
            $this->path = null;
        } else {
            $this->path = 'initial';
        }
    }

    /**
     * Get file.
     *
     * @return UploadedFile
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set path
     *
     * @param string $path
     *
     * @return TP
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
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
}
