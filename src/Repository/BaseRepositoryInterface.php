<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Default methods for all repositories (from Doctrine).
 */
interface BaseRepositoryInterface
{
    public function find(int $id): ?object;

    public function findAll(): array;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria): ?object;
}
