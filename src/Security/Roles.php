<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\Permission;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final readonly class Roles
{
    public function __construct(
        private RoleHierarchyInterface $roleHierarchy
    ) {
    }

    public const ALL_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_API_USER,
        self::ROLE_USER,
        self::ROLE_WEB_USER,
    ];
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

    /**
     * The source of truth for roles is security.role_hierarchy config.
     * We do not want to keep all user's roles if some of them are covered by others.
     *
     * @param array<string> $roles
     *
     * @return array<string>
     */
    public function collapseRoles(array $roles): array
    {
        $roles = array_unique($roles);

        $result = [];
        foreach ($roles as $role) {
            $isSubRole = false;

            foreach ($roles as $otherRole) {
                if ($role === $otherRole) {
                    continue;
                }

                $reachable = $this->roleHierarchy->getReachableRoleNames([$otherRole]);

                // if $role is a part of another role - it is a 'child' role
                if (in_array($role, $reachable, true)) {
                    $isSubRole = true;
                    break;
                }
            }

            if (!$isSubRole) {
                $result[] = $role;
            }
        }

        return $result;
    }
}
