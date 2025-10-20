<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserPermission;
use App\Repository\UserPermissionRepositoryInterface;
use App\Security\Permissions\CrmPermissions;
use App\Security\Permissions\Permissions;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

readonly class UserPermissionService
{
    public function __construct(
        private UserPermissionRepositoryInterface $permissionRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Update user permissions for a given scope.
     *
     * Uses "Delete All + Insert New" strategy instead of "Update or Create" because:
     *  1. Doctrine has no nice functionality for this.
     *  2. Simpler logic - no need to track what changed, what's new, what's removed
     *
     * Data is expected to be already validated via DTO.
     *
     * @param User $user
     * @param array $newPermissions
     */
    public function update(User $user, array $newPermissions): void
    {
        foreach ($newPermissions as $scope => $data) {
            // Only process CRM scope for now
            if (CrmPermissions::SCOPE !== $scope) {
                continue;
            }

            // Delete all existing permissions for this user and scope
            $this->permissionRepository->deleteBy([
                'user' => $user,
                'scope' => $scope,
            ]);

            // If both access flags are false, user has no access - just delete old permissions and skip
            if (!$data['access'][CrmPermissions::ACCESS_WEB] && !$data['access'][CrmPermissions::ACCESS_API]) {
                continue;
            }

            // If access is granted but permissions are empty - this is an error
            if (empty($data['permissions'])) {
                throw new LogicException('Cannot grant access without any permissions');
            }

            // Check if at least one entity has read permission
            $hasAnyReadPermission = false;
            foreach ($data['permissions'] as $entity => $actions) {
                $enabledActions = $this->getEnabledActions($actions);
                $actionsWithHierarchy = $this->applyPermissionHierarchy($enabledActions);

                if (in_array(CrmPermissions::READ, $actionsWithHierarchy, true)) {
                    $hasAnyReadPermission = true;
                    break;
                }
            }

            if (!$hasAnyReadPermission) {
                throw new LogicException('At least one entity must have read permission when access is granted');
            }

            $access = $this->resolveAccessType($data['access']);

            // Insert new permissions for each entity
            foreach ($data['permissions'] as $entity => $actions) {
                $enabledActions = $this->getEnabledActions($actions);
                $actionsWithHierarchy = $this->applyPermissionHierarchy($enabledActions);

                // If 'read' is not allowed (even after hierarchy), skip this entity entirely
                if (!in_array(CrmPermissions::READ, $actionsWithHierarchy, true)) {
                    continue;
                }

                $permission = $this->createPermission($user, $scope, $access, $entity, $actionsWithHierarchy);
                $this->em->persist($permission);
            }
        }

        // Execute all database operations
        $this->em->flush();
    }

    /**
     * Resolve access type based on enabled flags.
     *
     * @param array $accessFlags ['web' => bool, 'api' => bool]
     *
     * @return string 'all', 'web', or 'api'
     */
    private function resolveAccessType(array $accessFlags): string
    {
        if ($accessFlags[CrmPermissions::ACCESS_WEB] && $accessFlags[CrmPermissions::ACCESS_API]) {
            return CrmPermissions::ACCESS_ALL;
        }

        return $accessFlags[CrmPermissions::ACCESS_WEB] ? CrmPermissions::ACCESS_WEB : CrmPermissions::ACCESS_API;
    }

    /**
     * Filter only enabled actions (those with true values).
     *
     * @param array $actions ['read' => bool, 'write' => bool, ...]
     *
     * @return array List of enabled action names
     */
    private function getEnabledActions(array $actions): array
    {
        return array_keys(array_filter($actions, fn ($v) => true === $v));
    }

    /**
     * Apply permission hierarchy rules.
     *
     * Hierarchy (denormalized for MSA simplicity):
     *  - 'write' requires 'read'
     *  - 'delete' requires 'write' + 'read'
     *
     * @param array $actions List of action names
     *
     * @return array List of actions with hierarchy applied
     */
    private function applyPermissionHierarchy(array $actions): array
    {
        $result = $actions;

        // If 'write' is present, ensure 'read' is included
        if (in_array(CrmPermissions::WRITE, $actions, true) && !in_array(CrmPermissions::READ, $actions, true)) {
            $result[] = CrmPermissions::READ;
        }

        // If 'delete' is present, ensure 'write' and 'read' are included
        if (in_array(CrmPermissions::DELETE, $actions, true)) {
            if (!in_array(CrmPermissions::WRITE, $actions, true)) {
                $result[] = CrmPermissions::WRITE;
            }
            if (!in_array(CrmPermissions::READ, $actions, true)) {
                $result[] = CrmPermissions::READ;
            }
        }

        return array_unique($result);
    }

    /**
     * Create a UserPermission entity.
     *
     * @param User $user
     * @param string $scope
     * @param string $access
     * @param string $entity
     * @param array $actions
     *
     * @return UserPermission
     */
    private function createPermission(
        User $user,
        string $scope,
        string $access,
        string $entity,
        array $actions,
    ): UserPermission {
        return (new UserPermission())
            ->setUser($user)
            ->setScope($scope)
            ->setAccess($access)
            ->setEntity($entity)
            ->setAction($actions);
    }

    /**
     * Get all permissions for a user.
     *
     * Returns a structure with all possible permissions (from Permissions::all())
     * where flags are set to true for permissions the user has in DB.
     *
     * @param User $user
     *
     * @return array
     */
    public function getUserPermissions(User $user): array
    {
        // Get template with all possible permissions (all flags set to false)
        $permissions = Permissions::all();

        // Fetch what is set within DB
        $userPermissions = $this->permissionRepository->findBy(['user' => $user]);

        // Map DB records into the permissions structure
        return $this->mapUserPermissions($permissions, $userPermissions);
    }

    /**
     * Map UserPermission entities into permissions array structure.
     *
     * Takes a template array and applies user permissions from DB,
     * setting flags to true where user has permissions.
     *
     * @param array $permissions Template array with all possible permissions
     * @param UserPermission[] $userPermissions User's permissions from DB
     *
     * @return array Permissions array with user's permissions applied
     */
    private function mapUserPermissions(array $permissions, array $userPermissions): array
    {
        /** @var UserPermission $item */
        foreach ($userPermissions as $item) {
            $scope = $item->getScope();

            // Apply access flags (web/api or both)
            $permissions[$scope]['access'] = $this->applyAccessFlags(
                $permissions[$scope]['access'],
                $item->getAccess()
            );

            // Apply entity permissions (read, write, delete, etc.)
            $permissions[$scope]['permissions'][$item->getEntity()] = $this->applyEntityPermissions(
                $permissions[$scope]['permissions'][$item->getEntity()],
                $item->getAction()
            );
        }

        return $permissions;
    }

    /**
     * Apply access flags based on stored access type.
     *
     * @param array $currentAccess Current access flags ['web' => bool, 'api' => bool]
     * @param string $accessType Stored access type: 'all', 'web', or 'api'
     *
     * @return array Updated access flags
     */
    private function applyAccessFlags(array $currentAccess, string $accessType): array
    {
        if ('all' === $accessType) {
            return ['web' => true, 'api' => true];
        }

        $currentAccess[$accessType] = true;

        return $currentAccess;
    }

    /**
     * Apply entity permissions (read, write, delete, etc.).
     *
     * @param array $currentPermissions Current permissions ['read' => bool, 'write' => bool, ...]
     * @param array $allowedActions Actions user is allowed to perform
     *
     * @return array Updated permissions
     */
    private function applyEntityPermissions(array $currentPermissions, array $allowedActions): array
    {
        return array_replace(
            $currentPermissions,
            array_fill_keys($allowedActions, true)
        );
    }
}
