<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CrmRefreshToken;
use App\Entity\User;
use App\Exceptions\AuthException;
use App\Repository\CrmRefreshTokenRepositoryInterface;
use App\Service\CrmAuthService;
use App\Service\CrmTokenService;
use App\Service\UserPermissionService;
use DateTimeImmutable;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

class CrmAuthServiceTest extends TestCase
{
    private JWTTokenManagerInterface $jwt;
    private CrmTokenService $crmTokenService;
    private CrmRefreshTokenRepositoryInterface $refreshTokenRepository;
    private UserPermissionService $userPermissionService;
    private CrmAuthService $service;

    protected function setUp(): void
    {
        $this->jwt = $this->createMock(JWTTokenManagerInterface::class);
        $this->crmTokenService = $this->createMock(CrmTokenService::class);
        $this->refreshTokenRepository = $this->createMock(CrmRefreshTokenRepositoryInterface::class);
        $this->userPermissionService = $this->createMock(UserPermissionService::class);

        // Mock getUserPermissions to return sample permissions
        $this->userPermissionService
            ->method('getUserPermissions')
            ->willReturn([
                'crm' => [
                    'access' => ['web' => true, 'api' => true],
                    'permissions' => [
                        'lead' => ['read' => true, 'write' => true, 'delete' => false, 'import' => false, 'export' => false],
                        'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                        'deal' => ['read' => false, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    ],
                ],
            ]);

        $this->service = new CrmAuthService(
            $this->jwt,
            $this->crmTokenService,
            $this->refreshTokenRepository,
            $this->userPermissionService
        );
    }

    public function testExchangeTokenSuccess(): void
    {
        $user = (new User())
            ->setId(1)
            ->setName('Test User')
            ->setEmail('test@example.com');

        $exchangeToken = 'valid-exchange-token';

        // Mock exchange token validation
        $this->crmTokenService
            ->expects($this->once())
            ->method('validateAndConsume')
            ->with($exchangeToken)
            ->willReturn($user);

        // Mock JWT creation for access token
        $this->jwt
            ->expects($this->exactly(2))
            ->method('createFromPayload')
            ->willReturnOnConsecutiveCalls(
                'mock-access-token',
                'mock-refresh-token'
            );

        // Mock refresh token save
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (CrmRefreshToken $token) use ($user) {
                $this->assertSame($user, $token->getUser());
                $this->assertNotEmpty($token->getId());
                $this->assertInstanceOf(DateTimeImmutable::class, $token->getCreatedAt());
                $this->assertInstanceOf(DateTimeImmutable::class, $token->getExpiresAt());
                $this->assertFalse($token->isRevoked());

                return true;
            }));

        $result = $this->service->exchangeToken($exchangeToken);

        $this->assertEquals('mock-access-token', $result->getAccessToken());
        $this->assertEquals('mock-refresh-token', $result->getRefreshToken());
        $this->assertEquals(86400, $result->getAccessTtl());
        $this->assertEquals(2592000, $result->getRefreshTtl());
        $this->assertInstanceOf(DateTimeImmutable::class, $result->getRefreshExpiresAt());
    }

    public function testExchangeTokenInvalid(): void
    {
        $exchangeToken = 'invalid-exchange-token';

        $this->crmTokenService
            ->expects($this->once())
            ->method('validateAndConsume')
            ->with($exchangeToken)
            ->willThrowException(new AuthException('Invalid or expired exchange token'));

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired exchange token');

        $this->service->exchangeToken($exchangeToken);
    }

    public function testRefreshTokensSuccess(): void
    {
        $user = (new User())
            ->setId(1)
            ->setName('Test User')
            ->setEmail('test@example.com');

        $jti = 'test-jti-uuid';
        $refreshToken = 'valid-refresh-jwt-token';

        $refreshEntity = (new CrmRefreshToken())
            ->setId($jti)
            ->setUser($user)
            ->setCreatedAt(new DateTimeImmutable())
            ->setExpiresAt(new DateTimeImmutable('+30 days'));

        // Mock JWT parse
        $this->jwt
            ->expects($this->once())
            ->method('parse')
            ->with($refreshToken)
            ->willReturn([
                'jti' => $jti,
                'user_id' => 1,
                'exp' => (new DateTimeImmutable('+30 days'))->getTimestamp(),
            ]);

        // Mock finding valid refresh token in DB
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findValidByJti')
            ->with($jti)
            ->willReturn($refreshEntity);

        // Mock revoke old refresh token
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('revokeByJti')
            ->with($jti);

        // Mock JWT creation for new tokens
        $this->jwt
            ->expects($this->exactly(2))
            ->method('createFromPayload')
            ->willReturnOnConsecutiveCalls(
                'new-access-token',
                'new-refresh-token'
            );

        // Mock save new refresh token
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (CrmRefreshToken $token) use ($user) {
                $this->assertSame($user, $token->getUser());
                $this->assertNotEmpty($token->getId());

                return true;
            }));

        $result = $this->service->refreshTokens($refreshToken);

        $this->assertEquals('new-access-token', $result->getAccessToken());
        $this->assertEquals('new-refresh-token', $result->getRefreshToken());
        $this->assertEquals(86400, $result->getAccessTtl());
        $this->assertEquals(2592000, $result->getRefreshTtl());
    }

    public function testRefreshTokensInvalidFormat(): void
    {
        $refreshToken = 'malformed-jwt';

        $this->jwt
            ->expects($this->once())
            ->method('parse')
            ->with($refreshToken)
            ->willThrowException(new Exception('Invalid JWT'));

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid refresh token format');

        $this->service->refreshTokens($refreshToken);
    }

    public function testRefreshTokensMissingJti(): void
    {
        $refreshToken = 'valid-jwt-without-jti';

        $this->jwt
            ->expects($this->once())
            ->method('parse')
            ->with($refreshToken)
            ->willReturn(['user_id' => 1]); // Missing jti

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid refresh token payload');

        $this->service->refreshTokens($refreshToken);
    }

    public function testRefreshTokensExpired(): void
    {
        $jti = 'expired-jti';
        $refreshToken = 'expired-refresh-token';

        $this->jwt
            ->expects($this->once())
            ->method('parse')
            ->with($refreshToken)
            ->willReturn([
                'jti' => $jti,
                'user_id' => 1,
            ]);

        // Token not found in DB (expired or revoked)
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findValidByJti')
            ->with($jti)
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');

        $this->service->refreshTokens($refreshToken);
    }
}
