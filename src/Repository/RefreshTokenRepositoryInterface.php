<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;

interface RefreshTokenRepositoryInterface extends BaseRepositoryInterface
{
    public function save(RefreshToken $refreshToken): void;

    public function findValid(string $refreshId): ?RefreshToken;
}
