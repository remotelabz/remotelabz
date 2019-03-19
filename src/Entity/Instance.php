<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InstanceRepository")
 */
class Instance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Activity")
     * @ORM\JoinColumn(nullable=false)
     */
    private $activity;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $processName;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $storagePath;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Network", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $network;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Network", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $userNetwork;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getProcessName(): ?string
    {
        return $this->processName;
    }

    public function setProcessName(string $processName): self
    {
        $this->processName = $processName;

        return $this;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): self
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getNetwork(): ?Network
    {
        return $this->network;
    }

    public function setNetwork(Network $network): self
    {
        $this->network = $network;

        return $this;
    }

    public function getUserNetwork(): ?Network
    {
        return $this->userNetwork;
    }

    public function setUserNetwork(Network $userNetwork): self
    {
        $this->userNetwork = $userNetwork;

        return $this;
    }

    public static function create(): self
    {
        $instance = new Instance();

        return $instance;
    }
}
