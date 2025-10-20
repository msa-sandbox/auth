<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CrmExchangeToken;
use App\Entity\User;
use App\Exceptions\AuthException;
use App\Repository\CrmExchangeTokenRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\CrmTokenService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CrmTokenServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private CrmExchangeTokenRepositoryInterface $repository;
    private CrmTokenService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->repository = $this->createMock(CrmExchangeTokenRepositoryInterface::class);
        $this->service = new CrmTokenService($this->userRepository, $this->repository);
    }

    public function testGenerateExchangeToken(): void
    {
        $user = (new User())
            ->setId(1)
            ->setName('Test User')
            ->setEmail('test@example.com');

        $this->userRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (CrmExchangeToken $token) use ($user) {
                $this->assertSame($user, $token->getUser());
                $this->assertNotEmpty($token->getId());
                $this->assertNotEmpty($token->getTokenHash());
                $this->assertEquals(36, strlen($token->getId())); // UUID length
                $this->assertEquals(64, strlen($token->getTokenHash())); // SHA-256 hash length
                $this->assertInstanceOf(DateTimeImmutable::class, $token->getCreatedAt());
                $this->assertInstanceOf(DateTimeImmutable::class, $token->getExpiresAt());
                $this->assertNull($token->getUsedAt());

                return true;
            }));

        $dto = $this->service->generateExchangeToken(1);

        $this->assertNotEmpty($dto->getToken());
        $this->assertStringContainsString('.', $dto->getToken());
        $this->assertInstanceOf(DateTimeImmutable::class, $dto->getExpiresAt());
        $this->assertEquals(600, $dto->getTtl());
    }

    public function testValidateAndConsumeSuccess(): void
    {
        $user = (new User())
            ->setId(1)
            ->setName('Test User')
            ->setEmail('test@example.com');

        $token = 'test-uuid.randomBase64String';
        $tokenHash = hash('sha256', $token);

        $entity = (new CrmExchangeToken())
            ->setId('test-uuid')
            ->setUser($user)
            ->setTokenHash($tokenHash)
            ->setCreatedAt(new DateTimeImmutable())
            ->setExpiresAt(new DateTimeImmutable('+5 minutes'));

        $this->repository
            ->expects($this->once())
            ->method('findValidByHash')
            ->with($tokenHash)
            ->willReturn($entity);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (CrmExchangeToken $token) {
                $this->assertTrue($token->isUsed());
                $this->assertInstanceOf(DateTimeImmutable::class, $token->getUsedAt());

                return true;
            }));

        $resultUser = $this->service->validateAndConsume($token);

        $this->assertSame($user, $resultUser);
    }

    public function testValidateExpiredToken(): void
    {
        $token = 'test-uuid.randomBase64String';
        $tokenHash = hash('sha256', $token);

        $this->repository
            ->expects($this->once())
            ->method('findValidByHash')
            ->with($tokenHash)
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired exchange token');

        $this->service->validateAndConsume($token);
    }

    public function testValidateInvalidToken(): void
    {
        $token = 'invalid-token';
        $tokenHash = hash('sha256', $token);

        $this->repository
            ->expects($this->once())
            ->method('findValidByHash')
            ->with($tokenHash)
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired exchange token');

        $this->service->validateAndConsume($token);
    }

    public function testGeneratedTokenCanBeValidated(): void
    {
        $user = (new User())
            ->setId(1)
            ->setName('Test User')
            ->setEmail('test@example.com');

        $this->userRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $capturedEntity = null;

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (CrmExchangeToken $token) use (&$capturedEntity) {
                $capturedEntity = $token;
            });

        $dto = $this->service->generateExchangeToken(1);

        // Simulate finding the token
        $tokenHash = hash('sha256', $dto->getToken());
        $this->assertEquals($tokenHash, $capturedEntity->getTokenHash());
    }
}
