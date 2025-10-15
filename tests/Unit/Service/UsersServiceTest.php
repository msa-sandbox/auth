<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Exceptions\InfrastructureException;
use App\Infrastructure\Kafka\KafkaProducer;
use App\Repository\UserRepositoryInterface;
use App\Security\Roles;
use App\Service\UsersService;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UsersServiceTest extends KernelTestCase
{
    /**
     * Test normal case of setting new role for user.
     */
    public function testSetNewRoleUpdatesUser(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Tester')
            ->setRoles(['ROLE_USER']);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);
        $repository->expects($this->once())
            ->method('save')
            ->with($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        $roles = $this->createMock(Roles::class);
        $roles->method('collapseRoles')
            ->willReturn(['ROLE_ADMIN']);

        $kafka = $this->createMock(KafkaProducer::class);
        $kafka->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($payload) {
                return 'user.role.changed' === $payload['event']
                    && $payload['new_roles'] === ['ROLE_ADMIN'];
            }))
        ;

        $logger = $this->createMock(LoggerInterface::class);

        $service = new UsersService($repository, $roles, $kafka, $em, $logger);
        $service->setNewRole(1, ['ROLE_ADMIN']);

        $this->assertSame(['ROLE_ADMIN'], $user->getRoles());
    }

    /**
     * Test a case that exception is thrown (kafka is not available) and transaction is reverted.
     */
    public function testSetNewRoleUpdatesUserFail(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Tester')
            ->setRoles(['ROLE_USER']);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);
        $repository->expects($this->once())
            ->method('save')
            ->with($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->never())->method('commit');
        $em->expects($this->once())->method('rollback');
        $em->expects($this->once())->method('clear');

        $roles = $this->createMock(Roles::class);
        $roles->method('collapseRoles')
            ->willReturn(['ROLE_ADMIN']);

        $kafka = $this->createMock(KafkaProducer::class);
        $kafka->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($payload) {
                return 'user.role.changed' === $payload['event']
                    && $payload['new_roles'] === ['ROLE_ADMIN'];
            }))
            ->will($this->throwException(new RuntimeException('Failed to send message to Kafka')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new UsersService($repository, $roles, $kafka, $em, $logger);

        $this->expectException(InfrastructureException::class);
        $service->setNewRole(1, ['ROLE_ADMIN']);
    }

    public function testSetNewRoleThrowsIfUserNotFound(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('find')->willReturn(null);

        $roles = $this->createMock(Roles::class);
        $kafka = $this->createMock(KafkaProducer::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new UsersService($repository, $roles, $kafka, $em, $logger);

        $this->expectException(LogicException::class);
        $service->setNewRole(99, ['ROLE_ADMIN']);
    }
}
