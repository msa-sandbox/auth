<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Exceptions\InfrastructureException;
use App\Infrastructure\Kafka\KafkaProducer;
use App\Repository\UserPermissionRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\UserPermissionService;
use App\Service\UsersService;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UsersServiceTest extends KernelTestCase
{
    /**
     * Test normal case of setting new permissions for user.
     */
    public function testSetNewPermissionsUpdatesUser(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Tester')
            ->setRoles(['ROLE_USER']);

        $permissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'deal' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $permissionService = $this->createMock(UserPermissionService::class);
        $permissionService->expects($this->once())
            ->method('update')
            ->with($user, $permissions);

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        $kafka = $this->createMock(KafkaProducer::class);
        $kafka->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($payload) {
                return 'user.permissions.changed' === $payload['event']
                    && $payload['user_id'] === 1;
            }));

        $logger = $this->createMock(LoggerInterface::class);

        $service = new UsersService($permissionService, $kafka, $userRepository, $permissionRepository, $em, $logger);
        $service->setNewPermissions(1, $permissions);
    }

    /**
     * Test a case that exception is thrown (kafka is not available) and transaction is reverted.
     */
    public function testSetNewPermissionsFailsWhenKafkaUnavailable(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Tester')
            ->setRoles(['ROLE_USER']);

        $permissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'deal' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $permissionService = $this->createMock(UserPermissionService::class);
        $permissionService->expects($this->once())
            ->method('update')
            ->with($user, $permissions);

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->never())->method('commit');
        $em->expects($this->once())->method('rollback');
        $em->expects($this->once())->method('clear');

        $kafka = $this->createMock(KafkaProducer::class);
        $kafka->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($payload) {
                return 'user.permissions.changed' === $payload['event']
                    && $payload['user_id'] === 1;
            }))
            ->will($this->throwException(new RuntimeException('Failed to send message to Kafka')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new UsersService($permissionService, $kafka, $userRepository, $permissionRepository, $em, $logger);

        $this->expectException(InfrastructureException::class);
        $service->setNewPermissions(1, $permissions);
    }

    public function testSetNewPermissionsThrowsIfUserNotFound(): void
    {
        $permissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'deal' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('find')->willReturn(null);

        $permissionService = $this->createMock(UserPermissionService::class);
        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $kafka = $this->createMock(KafkaProducer::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new UsersService($permissionService, $kafka, $userRepository, $permissionRepository, $em, $logger);

        $this->expectException(LogicException::class);
        $service->setNewPermissions(99, $permissions);
    }
}
