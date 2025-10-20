<?php

declare(strict_types=1);

namespace App\Http\Dto;

use App\Security\Permissions\CrmPermissions;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CrmPermissionsDto
{
    private AccessDto $access;
    private array $permissions;

    public function __construct(mixed $access, mixed $permissions)
    {
        // Create nested DTO immediately so #[Assert\Valid] can validate it
        $this->access = is_array($access)
            ? new AccessDto(
                web: $access[CrmPermissions::ACCESS_WEB] ?? null,
                api: $access[CrmPermissions::ACCESS_API] ?? null,
            )
            : $access;

        $this->permissions = (array) $permissions;
    }

    #[Assert\NotBlank(message: 'Missing access configuration')]
    #[Assert\Valid]
    public function getAccess(): AccessDto
    {
        return $this->access;
    }

    #[Assert\NotBlank(message: 'Missing permissions configuration')]
    #[Assert\Type('array', message: 'Permissions must be an array')]
    #[Assert\All([
        new Assert\Collection([
            'fields' => [
                CrmPermissions::READ => new Assert\Type('bool'),
                CrmPermissions::WRITE => new Assert\Type('bool'),
                CrmPermissions::DELETE => new Assert\Type('bool'),
                CrmPermissions::IMPORT => new Assert\Type('bool'),
                CrmPermissions::EXPORT => new Assert\Type('bool'),
            ],
            'allowExtraFields' => false,
            'allowMissingFields' => false,
        ]),
    ])]
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function toArray(): array
    {
        return [
            'access' => $this->getAccess()->toArray(),
            'permissions' => $this->getPermissions(),
        ];
    }
}
