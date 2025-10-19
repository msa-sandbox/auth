<?php

namespace App\Entity;

use App\Repository\RefreshSessionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshSessionRepository::class)]
class RefreshSession
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private DateTimeImmutable $created_at;

    #[ORM\Column]
    private DateTimeImmutable $expires_at;

    #[ORM\Column]
    private ?DateTimeImmutable $last_used_at = null;

    #[ORM\Column]
    private bool $revoked = false;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expires_at;
    }

    public function setExpiresAt(DateTimeImmutable $expires_at): self
    {
        $this->expires_at = $expires_at;

        return $this;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->last_used_at;
    }

    public function setLastUsedAt(DateTimeImmutable $last_used_at): self
    {
        $this->last_used_at = $last_used_at;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked = true): self
    {
        $this->revoked = $revoked;

        return $this;
    }

    public function markUsed(): self
    {
        $this->last_used_at = new DateTimeImmutable();

        return $this;
    }
}
