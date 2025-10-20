<?php

declare(strict_types=1);

namespace App\Security\Permissions;

/**
 * Define permissions for CRM.
 */
class CrmPermissions
{
    public const SCOPE = 'crm';

    // System permissions
    public const ACCESS_WEB = 'web';
    public const ACCESS_API = 'api';
    public const ACCESS_ALL = 'all';

    // Entities (crm)
    public const LEAD = 'lead';
    public const CONTACT = 'contact';
    public const DEAL = 'deal';

    // Actions (crm)
    public const READ = 'read';
    public const WRITE = 'write';
    public const DELETE = 'delete';
    public const IMPORT = 'import';
    public const EXPORT = 'export';

    /**
     * @return array
     */
    public static function all(): array
    {
        $access = [self::ACCESS_WEB => false, self::ACCESS_API => false];
        $entities = [self::LEAD, self::CONTACT, self::DEAL];
        $actions = [
            self::READ => false, self::WRITE => false, self::DELETE => false,
            self::IMPORT => false, self::EXPORT => false,
        ];

        $permissions = [];
        foreach ($entities as $entity) {
            $permissions[$entity] = $actions;
        }

        return [
            self::SCOPE => [
                'access' => $access,
                'permissions' => $permissions,
            ],
        ];
    }
}
