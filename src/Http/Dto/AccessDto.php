<?php

declare(strict_types=1);

namespace App\Http\Dto;

use App\Security\Permissions\CrmPermissions;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AccessDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Missing web access flag')]
        #[Assert\Type('bool', message: 'Web access must be boolean')]
        private mixed $web,
        #[Assert\NotNull(message: 'Missing api access flag')]
        #[Assert\Type('bool', message: 'Api access must be boolean')]
        private mixed $api,
    ) {
    }

    public function getWeb(): bool
    {
        return (bool) $this->web;
    }

    public function getApi(): bool
    {
        return (bool) $this->api;
    }

    public function toArray(): array
    {
        return [
            CrmPermissions::ACCESS_WEB => $this->getWeb(),
            CrmPermissions::ACCESS_API => $this->getApi(),
        ];
    }
}
