<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exceptions\AuthException;
use App\Repository\RefreshTokenRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Security\AuthService;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthServiceTest extends KernelTestCase
{
    private $jwt;
    private $users;
    private $tokens;
    private $hasher;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->jwt = $this->createMock(JWTTokenManagerInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->tokens = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->hasher = $this->createMock(UserPasswordHasherInterface::class);

        $this->auth = new AuthService($this->jwt, $this->users, $this->tokens, $this->hasher);
    }

    public function testLoginSuccess(): void
    {
        $user = new User();
        $this->users
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $this->hasher
            ->method('isPasswordValid')
            ->with($user, 'password')
            ->willReturn(true);

        $this->jwt
            ->method('create')
            ->with($user)
            ->willReturn('jwt.token');

        $this->tokens
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(RefreshToken::class));

        $result = $this->auth->login('test@example.com', 'password');

        self::assertSame('jwt.token', $result->getAccessToken());
        self::assertNotEmpty($result->getRefreshId());
        self::assertGreaterThan(new DateTimeImmutable(), $result->getExpiresAt());
    }

    public function testLoginInvalidPassword(): void
    {
        $user = new User();
        $this->users->method('findOneBy')->willReturn($user);
        $this->hasher->method('isPasswordValid')->willReturn(false);

        $this->expectException(AuthException::class);
        $this->auth->login('test@example.com', 'bad');
    }

    public function testLoginUserNotFound(): void
    {
        $this->users->method('findOneBy')->willReturn(null);

        $this->expectException(AuthException::class);
        $this->auth->login('notfound@example.com', 'pass');
    }
}
