<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserPermission;
use App\Repository\UserPermissionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

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
            if ('crm' !== $scope) {
                continue;
            }

            $access = $this->resolveAccessType($data['access']);

            // Delete all existing permissions for this user and scope
            $this->permissionRepository->deleteBy([
                'user' => $user,
                'scope' => $scope,
            ]);

            // Insert new permissions for each entity
            foreach ($data['permissions'] as $entity => $actions) {
                $enabledActions = $this->getEnabledActions($actions);

                // If 'read' is not allowed, skip this entity entirely
                if (!in_array('read', $enabledActions, true)) {
                    continue;
                }

                $actionsWithHierarchy = $this->applyPermissionHierarchy($enabledActions);

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
        if ($accessFlags['web'] && $accessFlags['api']) {
            return 'all';
        }

        return $accessFlags['web'] ? 'web' : 'api';
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
        if (in_array('write', $actions, true) && !in_array('read', $actions, true)) {
            $result[] = 'read';
        }

        // If 'delete' is present, ensure 'write' and 'read' are included
        if (in_array('delete', $actions, true)) {
            if (!in_array('write', $actions, true)) {
                $result[] = 'write';
            }
            if (!in_array('read', $actions, true)) {
                $result[] = 'read';
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
}
