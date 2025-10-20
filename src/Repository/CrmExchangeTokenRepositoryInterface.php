<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CrmExchangeToken;

interface CrmExchangeTokenRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param CrmExchangeToken $token
     *
     * @return void
     */
    public function save(CrmExchangeToken $token): void;

    /**
     * @param string $tokenHash
     *
     * @return CrmExchangeToken|null
     */
    public function findValidByHash(string $tokenHash): ?CrmExchangeToken;

    /**
     * @return int
     */
    public function cleanupExpired(): int;
}
