<?php

declare(strict_types=1);

namespace App\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetUserPermissionsRequestDto
{
    private mixed $crm;

    public function __construct(mixed $data)
    {
        $crmData = is_array($data) ? ($data['crm'] ?? null) : null;

        // Create nested DTO immediately so #[Assert\Valid] can validate it
        $this->crm = is_array($crmData)
            ? new CrmPermissionsDto(
                access: $crmData['access'] ?? null,
                permissions: $crmData['permissions'] ?? null,
            )
            : $crmData;
    }

    #[Assert\NotBlank(message: 'Missing crm permissions')]
    #[Assert\Valid]
    public function getCrm(): CrmPermissionsDto
    {
        return $this->crm;
    }

    /**
     * Get permissions in the format expected by UserPermissionService.
     */
    public function toArray(): array
    {
        return [
            'crm' => $this->getCrm()->toArray(),
        ];
    }
}
