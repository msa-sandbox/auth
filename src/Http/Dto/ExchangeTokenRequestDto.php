<?php

declare(strict_types=1);

namespace App\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ExchangeTokenRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Missing grant_type')]
        #[Assert\EqualTo(value: 'exchange_token', message: 'Invalid grant type. Expected: exchange_token')]
        private mixed $grant_type,
        #[Assert\NotBlank(message: 'Exchange token is required')]
        private mixed $exchange_token,
    ) {
    }

    public function getGrantType(): string
    {
        return (string) $this->grant_type;
    }

    public function getExchangeToken(): string
    {
        return (string) $this->exchange_token;
    }
}
