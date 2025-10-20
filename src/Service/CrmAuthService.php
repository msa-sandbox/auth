<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CrmRefreshToken;
use App\Entity\User;
use App\Exceptions\AuthException;
use App\Repository\CrmRefreshTokenRepositoryInterface;
use App\Security\Permissions\CrmPermissions;
use App\Service\Dto\CrmAuthResultDto;
use DateTimeImmutable;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class CrmAuthService
{
    private const ACCESS_TOKEN_TTL = 86400; // 24 hours
    private const REFRESH_TOKEN_TTL = 2592000; // 30 days

    public function __construct(
        private JWTTokenManagerInterface $jwt,
        private CrmTokenService $crmTokenService,
        private CrmRefreshTokenRepositoryInterface $refreshTokenRepository,
        private UserPermissionService $userPermissionService,
    ) {
    }

    /**
     * Exchange a one-time exchange token for access and refresh JWT tokens.
     *
     * @param string $exchangeToken
     *
     * @return CrmAuthResultDto
     *
     * @throws AuthException
     */
    public function exchangeToken(string $exchangeToken): CrmAuthResultDto
    {
        // Validate and consume exchange token
        $user = $this->crmTokenService->validateAndConsume($exchangeToken);

        return $this->createTokenPair($user);
    }

    /**
     * Refresh access and refresh tokens using a valid refresh JWT.
     *
     * @param string $refreshToken
     *
     * @return CrmAuthResultDto
     *
     * @throws AuthException
     */
    public function refreshTokens(string $refreshToken): CrmAuthResultDto
    {
        // Parse refresh JWT to extract jti
        try {
            $payload = $this->jwt->parse($refreshToken);
        } catch (Exception $exception) {
            throw new AuthException('Invalid refresh token format');
        }

        if (!isset($payload['jti']) || !isset($payload['user_id'])) {
            throw new AuthException('Invalid refresh token payload');
        }

        $jti = $payload['jti'];

        // Validate refresh token in database
        $refreshEntity = $this->refreshTokenRepository->findValidByJti($jti);
        if (!$refreshEntity) {
            throw new AuthException('Invalid or expired refresh token');
        }

        $user = $refreshEntity->getUser();

        // Revoke old refresh token
        $this->refreshTokenRepository->revokeByJti($jti);

        // Create a new token pair
        return $this->createTokenPair($user);
    }

    /**
     * Create a new pair of access and refresh tokens.
     *
     * @param User $user
     *
     * @return CrmAuthResultDto
     */
    private function createTokenPair(User $user): CrmAuthResultDto
    {
        // Get user permissions for CRM
        $permissions = $this->getCrmPermissionsForToken($user);

        // Create access JWT with permissions
        $accessToken = $this->jwt->createFromPayload($user, [
            'user_id' => $user->getId(),
            'permissions' => $permissions,
        ]);

        // Create refresh JWT with jti
        $jti = Uuid::v4()->toRfc4122();
        $refreshExpiresAt = new DateTimeImmutable('+'.self::REFRESH_TOKEN_TTL.' seconds');

        $refreshToken = $this->jwt->createFromPayload($user, [
            'jti' => $jti,
            'user_id' => $user->getId(),
            'exp' => $refreshExpiresAt->getTimestamp(),
        ]);

        // Save refresh token metadata to database
        $refreshEntity = (new CrmRefreshToken())
            ->setId($jti)
            ->setUser($user)
            ->setCreatedAt(new DateTimeImmutable())
            ->setExpiresAt($refreshExpiresAt);

        $this->refreshTokenRepository->save($refreshEntity);

        return new CrmAuthResultDto(
            $accessToken,
            $refreshToken,
            self::ACCESS_TOKEN_TTL,
            self::REFRESH_TOKEN_TTL,
            $refreshExpiresAt
        );
    }

    /**
     * Get CRM permissions for the token payload.
     * Returns only enabled permissions (with true values) for each entity.
     *
     * @param User $user
     *
     * @return array Entity => [action1, action2, ...] for enabled actions
     */
    private function getCrmPermissionsForToken(User $user): array
    {
        $allPermissions = $this->userPermissionService->getUserPermissions($user);

        // Extract only CRM permissions
        if (!isset($allPermissions[CrmPermissions::SCOPE]['permissions'])) {
            return [];
        }

        $crmPermissions = $allPermissions[CrmPermissions::SCOPE]['permissions'];
        $result = [];

        // For each entity, keep only actions that are set to true
        foreach ($crmPermissions as $entity => $actions) {
            $enabledActions = array_keys(array_filter($actions, fn ($enabled) => true === $enabled));
            if (!empty($enabledActions)) {
                $result[$entity] = $enabledActions;
            }
        }

        return $result;
    }
}
