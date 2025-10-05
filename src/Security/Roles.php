<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\Permission;

final class Roles
{
    public const ROLE_ADMIN    = 'ROLE_ADMIN';
    public const ROLE_API_USER = 'ROLE_API_USER';
    public const ROLE_USER     = 'ROLE_USER';
    public const ROLE_WEB_USER = 'ROLE_WEB_USER';

    public static function permissions(string $role): array
    {
        return match ($role) {
            self::ROLE_ADMIN => Permission::cases(),
            self::ROLE_API_USER => [
                Permission::LEAD_READ,
                Permission::CONTACT_READ,
            ],
            default => [],
        };
    }
}
