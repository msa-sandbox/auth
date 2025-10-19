<?php

declare(strict_types=1);

namespace App\Security;

readonly class Roles
{
    public function __construct(
    ) {
    }

    public const ALL_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_USER,
    ];
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';
}
