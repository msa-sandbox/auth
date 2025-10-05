<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param string $email
     *
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * @param User $user
     *
     * @return void
     */
    public function save(User $user): void;
}
