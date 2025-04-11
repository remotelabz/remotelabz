<?php

namespace App\Entity;

use App\Utils\Uuid;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Table(name: 'user_group')]
#[ORM\Entity(repositoryClass: 'App\Repository\GroupUserRepository')]
class GroupUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['group_tree'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Group', inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id')]
    #[Serializer\Groups(['user', 'api_get_user', 'api_get_lab_instance', 'api_users', 'api_get_lab'])]
    #[Serializer\Inline]
    private Group $group;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', inversedBy: '_groups', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    #[Serializer\Groups(['group_users', 'group_tree', 'group_explore', 'api_groups', 'api_get_group'])]
    #[Serializer\Inline]
    private User $user;

    #[ORM\Column(type: 'array')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private $permissions;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['group_users', 'group_tree', 'user', 'api_groups', 'api_get_group', 'api_get_user', 'api_users', 'api_get_lab'])]
    private ?string $role;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $createdAt;

    public function __construct(User $user, Group $group, ?string $role = Group::ROLE_USER, ?array $permissions = [])
    {
        $this->user = $user;
        $this->group = $group;
        $this->permissions = new ArrayCollection($permissions);
        $this->role = $role;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Returns user's specific permission array.
     *
     * @return Collection
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = new ArrayCollection($permissions);

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
