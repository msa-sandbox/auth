<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CrmRefreshToken;

interface CrmRefreshTokenRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param CrmRefreshToken $token
     *
     * @return void
     */
    public function save(CrmRefreshToken $token): void;

    /**
     * @param string $jti
     *
     * @return CrmRefreshToken|null
     */
    public function findValidByJti(string $jti): ?CrmRefreshToken;

    /**
     * @param string $jti
     *
     * @return void
     */
    public function revokeByJti(string $jti): void;

    /**
     * @return int
     */
    public function cleanupExpired(): int;
}
