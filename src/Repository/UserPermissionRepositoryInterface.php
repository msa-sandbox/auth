<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserPermission;

interface UserPermissionRepositoryInterface extends BaseRepositoryInterface
{
    public function save(UserPermission $userPermission): void;

    /**
     * Delete all records matching the given criteria.
     *
     * @param array $criteria Associative array of field => value pairs
     *
     * @return int Number of deleted records
     */
    public function deleteBy(array $criteria): int;
}
