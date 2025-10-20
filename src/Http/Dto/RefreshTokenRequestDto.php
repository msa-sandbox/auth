<?php

declare(strict_types=1);

namespace App\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RefreshTokenRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Missing grant_type')]
        #[Assert\EqualTo(value: 'refresh_token', message: 'Invalid grant type. Expected: refresh_token')]
        private mixed $grant_type,
        #[Assert\NotBlank(message: 'Refresh token is required')]
        private mixed $refresh_token,
    ) {
    }

    public function getGrantType(): string
    {
        return (string) $this->grant_type;
    }

    public function getRefreshToken(): string
    {
        return (string) $this->refresh_token;
    }
}
