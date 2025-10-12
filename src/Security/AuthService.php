<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exceptions\AuthException;
use App\Repository\RefreshTokenRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Security\Dto\AuthResultDto;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

readonly class AuthService
{
    public function __construct(
        private JWTTokenManagerInterface $jwt,
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @throws AuthException
     */
    public function login(string $email, string $password): AuthResultDto
    {
        /** @var User|null $user */
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new AuthException('Invalid credentials');
        }

        return $this->createTokens($user);
    }

    /**
     * @throws AuthException
     */
    public function refresh(string $refreshId): AuthResultDto
    {
        $token = $this->refreshTokenRepository->findValid($refreshId);
        if (!$token) {
            throw new AuthException('Invalid refresh token');
        }
        $user = $token->getUser();

        $token->markUsed()->setRevoked();
        $this->refreshTokenRepository->save($token);

        return $this->createTokens($user);
    }

    /**
     * @throws AuthException
     */
    public function logout(string $refreshId): void
    {
        /** @var RefreshToken|null $token */
        $token = $this->refreshTokenRepository->findOneBy(['id' => $refreshId]);
        if (!$token) {
            throw new AuthException('Invalid refresh token');
        }

        $token->markUsed()->setRevoked();
        $this->refreshTokenRepository->save($token);
    }

    /**
     * Create a new tokens pair.
     * Refresh token is saved within DB.
     * JWT and refresh_id should be returned to the client.
     *
     * @param User $user
     *
     * @return AuthResultDto
     */
    private function createTokens(User $user): AuthResultDto
    {
        // Create JWT
        $accessToken = $this->jwt->create($user);

        // Create a refresh session
        $refreshId = Uuid::v4()->toRfc4122();

        // Create a refresh token
        $token = (new RefreshToken())
            ->setId($refreshId)
            ->setUser($user)
            ->setCreatedAt(new DateTimeImmutable())
            ->setExpiresAt(new DateTimeImmutable('+7 days'));

        $this->refreshTokenRepository->save($token);

        return new AuthResultDto(
            $accessToken,
            $token->getId(),
            $token->getExpiresAt()
        );
    }
}
