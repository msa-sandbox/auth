<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

final readonly class UsersService
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {
    }

    /**
     * Get all users
     */
    public function handle(): array
    {
        $users = $this->repository->findAll();

        return array_map(fn (User $user) => [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
        ], $users);
    }
}
