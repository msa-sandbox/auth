<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exceptions\InfrastructureException;
use App\Infrastructure\Kafka\KafkaProducer;
use App\Repository\UserRepositoryInterface;
use App\Security\Roles;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class UsersService
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private Roles $roles,
        private KafkaProducer $kafkaProducer,
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
     * @param int $id
     * @param array $newRoles
     *
     * @return void
     *
     * @throws InfrastructureException
     */
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

        $this->em->beginTransaction();

        try {
            // Let's collapse roles to avoid logical duplicates
            $collapsed = $this->roles->collapseRoles($newRoles);

            $user->setRoles($collapsed);
            $this->repository->save($user);

            $this->fireRoleCHangedEvent($user, $collapsed);

            $this->em->commit();
        } catch (Throwable $e) {
            $this->em->rollback();
            $this->em->clear();

            $this->logger->error('Role update failed, transaction rolled back', [
                'userId' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new InfrastructureException('Failed to update roles');
        }
    }

    /**
     * Create event within Kafka.
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
