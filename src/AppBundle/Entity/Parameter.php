<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Parameter
 *
 * @ORM\Table(name="parameter")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ParameterRepository")
 */
class Parameter
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
     * @ORM\Column(name="taille_memoire", type="string", length=40)
     */
    private $taille_memoire;

    /**
     * @var string
     *
     * @ORM\Column(name="taille_disque", type="string", length=40)
     */

    private $taille_disque;

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
     * Set tailleMemoire
     *
     * @param string $tailleMemoire
     *
     * @return Parameter
     */
    public function setTailleMemoire($tailleMemoire)
    {
        $this->taille_memoire = $tailleMemoire;

        return $this;
    }

    /**
     * Get tailleMemoire
     *
     * @return string
     */
    public function getTailleMemoire()
    {
        return $this->taille_memoire;
    }

    /**
     * Set tailleDisque
     *
     * @param string $tailleDisque
     *
     * @return Parameter
     */
    public function setTailleDisque($tailleDisque)
    {
        $this->taille_disque = $tailleDisque;

        return $this;
    }

    /**
     * Get tailleDisque
     *
     * @return string
     */
    public function getTailleDisque()
    {
        return $this->taille_disque;
    }
}
