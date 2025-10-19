<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserPermissionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPermissionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table]
#[ORM\Index(name: 'idx_user_scope', columns: ['user_id', 'scope'])]
#[ORM\UniqueConstraint(name: 'uniq_user_scope_access_entity', columns: ['user_id', 'scope', 'access', 'entity'])]
class UserPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 50, nullable: false)]
    private string $scope;              // crm | messenger | ...

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $access = null;     // all | web | api | ...

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $entity = null;     // lead | contact | ...

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $action = null;      // read | write | ...

    #[ORM\Column]
    private DateTimeImmutable $created_at;

    #[ORM\Column]
    private DateTimeImmutable $updated_at;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new DateTimeImmutable();
        $this->updated_at = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    public function getAccess(): ?string
    {
        return $this->access;
    }

    public function setAccess(string $access): self
    {
        $this->access = $access;

        return $this;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getAction(): ?array
    {
        return $this->action;
    }

    public function setAction(array $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(DateTimeImmutable $updated_at): self
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}
