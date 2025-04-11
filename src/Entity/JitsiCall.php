<?php

namespace App\Entity;

use Remotelabz\Message\Message\InstanceStateMessage;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: 'App\Repository\JitsiCallRepository')]
class JitsiCall
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab_instance'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab_instance'])]
    private $state;

    public function __construct()
    {
        $this->state = InstanceStateMessage::STATE_STOPPED;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function isStarted(): ?bool
    {
        return $this->state === InstanceStateMessage::STATE_STARTED;
    }

}