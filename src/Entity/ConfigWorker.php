<?php

namespace App\Entity;

use App\Repository\WorkerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * @ORM\Entity(repositoryClass=WorkerRepository::class)
 * @UniqueEntity("IPv4")
 * @UniqueEntity("queueName")
 */
class ConfigWorker
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *  @Serializer\Groups({ "api_get_worker_config"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\Ip(version="4")
     * @Assert\Unique
     * @Serializer\Groups({"api_get_device", "export_lab", "worker", "api_get_worker_config"})
     */
    private $IPv4;

     /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\Unique
     * @Serializer\Groups({"api_get_device", "export_lab", "worker", "api_get_worker_config"})
     */
    private $queueName;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\Groups({ "api_get_worker_config"})
     */
    private $available;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueueName(): ?string
    {
        return $this->queueName;
    }

    public function setQueueName(string $queueName): self
    {
        $this->queueName = $queueName;

        return $this;
    }

    public function getIPv4(): ?string
    {
        return $this->IPv4;
    }

    public function setIPv4(string $IPv4): self
    {
        $this->IPv4 = $IPv4;

        return $this;
    }

    public function getAvailable(): ?bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): self
    {
        $this->available = $available;

        return $this;
    }
}
