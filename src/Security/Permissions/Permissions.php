<?php

declare(strict_types=1);

namespace App\Security\Permissions;

class Permissions
{
    public static function all(): array
    {
        return CrmPermissions::all();
    }
}
