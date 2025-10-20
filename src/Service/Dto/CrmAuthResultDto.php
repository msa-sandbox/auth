<?php

declare(strict_types=1);

namespace App\Service\Dto;

use DateTimeImmutable;

final readonly class CrmAuthResultDto
{
    public function __construct(
        private string $accessToken,
        private string $refreshToken,
        private int $accessTtl,
        private int $refreshTtl,
        private DateTimeImmutable $refreshExpiresAt,
    ) {
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }

    public function getRefreshExpiresAt(): DateTimeImmutable
    {
        return $this->refreshExpiresAt;
    }
}
