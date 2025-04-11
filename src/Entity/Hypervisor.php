<?php

namespace App\Entity;

use App\Repository\HypervisorRepository;
use Symfony\Component\Validator\Constraints as Assert;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HypervisorRepository::class)]
class Hypervisor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_hypervisor', 'api_delete_os', 'api_get_device', 'export_lab', 'worker', 'sandbox', 'api_get_device_instance', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $id;

    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_hypervisor', 'api_delete_os', 'api_get_device', 'export_lab', 'worker', 'sandbox', 'api_get_device_instance', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
