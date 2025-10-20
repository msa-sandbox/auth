<?php

declare(strict_types=1);

namespace App\Service\Dto;

use DateTimeImmutable;

final readonly class CrmExchangeTokenDto
{
    public function __construct(
        private string $token,
        private DateTimeImmutable $expiresAt,
        private int $ttl,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
