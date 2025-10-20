<?php

declare(strict_types=1);

namespace App\Service\Dto;

use DateTimeImmutable;

final readonly class AuthResultDto
{
    public function __construct(
        private string $accessToken,
        private string $refreshId,
        private DateTimeImmutable $expiresAt,
        private int $ttl,
    ) {
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshId(): string
    {
        return $this->refreshId;
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
