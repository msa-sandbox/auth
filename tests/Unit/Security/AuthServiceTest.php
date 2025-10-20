<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\RefreshSession;
use App\Entity\User;
use App\Exceptions\AuthException;
use App\Metrics\MetricsCollector;
use App\Repository\RefreshSessionRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\AuthService;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthServiceTest extends KernelTestCase
{
    private $jwt;
    private $users;
    private $session;
    private $hasher;
    private $params;
    private $metrics;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->jwt = $this->createMock(JWTTokenManagerInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->session = $this->createMock(RefreshSessionRepositoryInterface::class);
        $this->hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        // Create real MetricsCollector with in-memory registry for testing
        $collectorRegistry = new CollectorRegistry(new InMemory());
        $this->metrics = new MetricsCollector();
        $this->metrics->init('auth_test', $collectorRegistry);

        // Mock JWT TTL parameter
        $this->params
            ->method('get')
            ->with('lexik_jwt_authentication.token_ttl')
            ->willReturn(3600);

        $this->auth = new AuthService($this->jwt, $this->users, $this->session, $this->hasher, $this->params, $this->metrics);
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
            ->method('createFromPayload')
            ->with($user, self::isType('array'))
            ->willReturn('jwt.token');

        $this->session
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(RefreshSession::class));

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
