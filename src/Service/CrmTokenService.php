<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CrmExchangeToken;
use App\Entity\User;
use App\Exceptions\AuthException;
use App\Repository\CrmExchangeTokenRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\Dto\CrmExchangeTokenDto;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

readonly class CrmTokenService
{
    private const TOKEN_TTL_SECONDS = 600; // 10 minutes

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CrmExchangeTokenRepositoryInterface $repository,
    ) {
    }

    /**
     * Generate a new exchange token for CRM API access.
     * Token format: {uuid}.{base64(random_bytes)} for security against brute-force.
     * Only the hash is stored in the database.
     *
     * @param int $userId
     *
     * @return CrmExchangeTokenDto
     */
    public function generateExchangeToken(int $userId): CrmExchangeTokenDto
    {
        /** @var User|null $user */
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new LogicException('User not found');
        }

        $uuid = Uuid::v4()->toRfc4122();
        $randomPart = base64_encode(random_bytes(32));
        $token = $uuid.'.'.$randomPart;

        $tokenHash = hash('sha256', $token);

        $entity = (new CrmExchangeToken())
            ->setId($uuid)
            ->setUser($user)
            ->setTokenHash($tokenHash)
            ->setCreatedAt(new DateTimeImmutable())
            ->setExpiresAt(new DateTimeImmutable('+'.self::TOKEN_TTL_SECONDS.' seconds'));

        $this->repository->save($entity);

        return new CrmExchangeTokenDto(
            $token,
            $entity->getExpiresAt(),
            self::TOKEN_TTL_SECONDS
        );
    }

    /**
     * Validate exchange token and mark it as used.
     * Returns the user associated with the token.
     *
     * @param string $token
     *
     * @return User
     *
     * @throws AuthException
     */
    public function validateAndConsume(string $token): User
    {
        $tokenHash = hash('sha256', $token);

        $entity = $this->repository->findValidByHash($tokenHash);

        if (!$entity) {
            throw new AuthException('Invalid or expired exchange token');
        }

        $entity->markUsed();
        $this->repository->save($entity);

        return $entity->getUser();
    }
}
