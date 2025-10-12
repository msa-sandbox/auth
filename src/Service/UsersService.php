<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Infrastructure\Kafka\KafkaProducer;
use App\Manager\UserManager;
use App\Repository\UserRepositoryInterface;
use App\Security\Roles;
use DateTimeImmutable;
use LogicException;

final readonly class UsersService
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private UserManager $manager,
        private Roles $roles,
        private KafkaProducer $kafkaProducer,
    ) {
    }

    /**
     * Get all users
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

    public function setNewRole(int $id, array $newRoles): void
    {
        /** @var User|null $user */
        $user = $this->repository->find($id);
        if (!$user) {
            throw new LogicException('User not found');
        }

        // @toThink and implement after auth
        //        if (!$this->isGranted('ROLE_ADMIN')) {
        //            throw new AccessDeniedException('Only admins can modify roles.');
        //        }

        // Let's collapse roles to avoid logical duplicates
        $new = $this->roles->collapseRoles($newRoles);

        $this->manager->updateRoles($user, $new);

        $this->fireRoleCHangedEvent($user, $new);
    }

    /**
     * Create event within Kafka
     *
     * @param User $user
     * @param array $newRoles
     *
     * @return void
     */
    private function fireRoleCHangedEvent(User $user, array $newRoles): void
    {
        $event = [
            'event' => 'user.role.changed',
            'user_id' => $user->getId(),
            'new_roles' => $newRoles,
            'changed_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->kafkaProducer->send($event);
    }
}
