<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshSession;

interface RefreshSessionRepositoryInterface extends BaseRepositoryInterface
{
    public function save(RefreshSession $refreshToken): void;

    public function findValid(string $refreshId): ?RefreshSession;
}
