<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exceptions\InfrastructureException;
use App\Infrastructure\Kafka\KafkaProducer;
use App\Repository\UserRepositoryInterface;
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
     * Important moment with kafka. Usually there are 2 ways how to deal if we fail to send message to kafka.
     * 1. Add this even to some queue and retry later
     * 2. Rollback transaction.
     *
     * I choose the second option. Reason is only one: Permissions are critical data.
     * If we fail to notify other services about the change, they will work with stale data.
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
     * Get all permissions for a user.
     *
     * Delegates to UserPermissionService.
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

        return $this->userPermissionService->getUserPermissions($user);
    }
}
