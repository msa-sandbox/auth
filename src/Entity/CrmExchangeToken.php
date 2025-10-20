<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CrmExchangeTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmExchangeTokenRepository::class)]
#[ORM\Index(name: 'idx_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_expires_at', columns: ['expires_at'])]
class CrmExchangeToken
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 64)]
    private string $token_hash;

    #[ORM\Column]
    private DateTimeImmutable $created_at;

    #[ORM\Column]
    private DateTimeImmutable $expires_at;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $used_at = null;

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

    public function getTokenHash(): string
    {
        return $this->token_hash;
    }

    public function setTokenHash(string $token_hash): self
    {
        $this->token_hash = $token_hash;

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

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->used_at;
    }

    public function setUsedAt(?DateTimeImmutable $used_at): self
    {
        $this->used_at = $used_at;

        return $this;
    }

    public function markUsed(): self
    {
        $this->used_at = new DateTimeImmutable();

        return $this;
    }

    public function isUsed(): bool
    {
        return null !== $this->used_at;
    }

    public function isExpired(): bool
    {
        return $this->expires_at < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->isUsed() && !$this->isExpired();
    }
}
