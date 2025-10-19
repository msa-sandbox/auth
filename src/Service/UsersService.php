<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserPermission;
use App\Exceptions\InfrastructureException;
use App\Infrastructure\Kafka\KafkaProducer;
use App\Repository\UserPermissionRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Security\Permissions\Permissions;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class UsersService
{
    public function __construct(
        private UserPermissionService $userPermissionService,
        private KafkaProducer $kafkaProducer,
        private UserRepositoryInterface $repository,
        private UserPermissionRepositoryInterface $userPermissionRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get all users.
     */
    public function getAllUsers(): array
    {
        $users = $this->repository->findAll();

        return array_map(fn (User $user) => [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
        ], $users);
    }

    /**
     * Set new permissions for a user.
     * Currently, only admins can change permissions (checked on Controller level).
     *
     * @param int $id
     * @param array $newPermissions
     *
     * @return void
     *
     * @throws InfrastructureException
     */
    public function setNewPermissions(int $id, array $newPermissions): void
    {
        /** @var User|null $user */
        $user = $this->repository->find($id);
        if (!$user) {
            throw new LogicException('User not found');
        }

        $this->em->beginTransaction();

        try {
            // Set new permissions
            $this->userPermissionService->update($user, $newPermissions);

            $this->firePermissionsCHangedEvent($user);

            $this->em->commit();
        } catch (Throwable $e) {
            $this->em->rollback();
            $this->em->clear();

            $this->logger->error('Permissions update failed, transaction rolled back', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new InfrastructureException('Failed to update permissions');
        }
    }

    /**
     * Create event within Kafka.
     *
     * @param User $user
     *
     * @return void
     */
    private function firePermissionsCHangedEvent(User $user): void
    {
        $event = [
            'event' => 'user.permissions.changed',
            'user_id' => $user->getId(),
            'changed_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->kafkaProducer->send($event);
    }

    /**
     * Find all permissions that a user has and can have.
     *
     * Returns a structure with all possible permissions (from Permissions::all())
     * where flags are set to true for permissions the user has in DB.
     *
     * @param int $id
     *
     * @return array
     */
    public function getUserPermissions(int $id): array
    {
        /** @var User|null $user */
        $user = $this->repository->find($id);
        if (!$user) {
            throw new LogicException('User not found');
        }

        // Get template with all possible permissions (all flags set to false)
        $permissions = Permissions::all();

        // Fetch what is set within DB
        $userPermissions = $this->userPermissionRepository->findBy(['user' => $user]);

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
