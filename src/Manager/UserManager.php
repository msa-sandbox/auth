<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

final readonly class UserManager
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    public function updateRoles(User $user, array $roles): User
    {
        $user->setRoles($roles);

        $this->repository->save($user);

        return $user;
    }
}
