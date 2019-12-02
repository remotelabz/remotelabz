<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CourseRepository")
 */
class Course
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     */
    private $name;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\User", mappedBy="courses")
     * @Serializer\Groups({"details"})
     */
    private $users;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Activity", mappedBy="courses")
     * @Serializer\XmlList(inline=true, entry="activity")
     * @Serializer\Groups({"details"})
     */
    private $activities;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Group", mappedBy="course", orphanRemoval=true)
     */
    private $groups;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

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

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->addCourse($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->contains($user)) {
            $this->users->removeElement($user);
            $user->removeCourse($this);
        }

        return $this;
    }

    /**
     * Return only IDs of users.
     *
     * @return array|int[]
     */
    public function getUsersId(): array
    {
        $users = [];

        foreach ($this->users as $user) {
            $users[] = $user->getId();
        }

        return $users;
    }

    /**
     * @return Collection|Activity[]
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities[] = $activity;
            $activity->addCourse($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): self
    {
        if ($this->activities->contains($activity)) {
            $this->activities->removeElement($activity);
            $activity->removeCourse($this);
        }

        return $this;
    }

    /**
     * @return Collection|Group[]
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
            $group->setCourse($this);
        }

        return $this;
    }

    public function removeGroup(Group $group): self
    {
        if ($this->groups->contains($group)) {
            $this->groups->removeElement($group);
            // set the owning side to null (unless already changed)
            if ($group->getCourse() === $this) {
                $group->setCourse(null);
            }
        }

        return $this;
    }
}
