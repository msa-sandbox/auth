<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Infrastructure\Kafka\KafkaProducer;
use App\Manager\UserManager;
use App\Repository\UserRepositoryInterface;
use App\Security\Roles;
use App\Service\UsersService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UsersServiceTest extends KernelTestCase
{
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

        $manager = $this->createMock(UserManager::class);
        $manager->expects($this->once())
            ->method('updateRoles')
            ->with($user, ['ROLE_ADMIN']);

        $roles = $this->createMock(Roles::class);
        $roles->method('collapseRoles')
            ->willReturn(['ROLE_ADMIN']);

        $kafka = $this->createMock(KafkaProducer::class);
        $kafka->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($payload) {
                return $payload['event'] === 'user.role.changed'
                    && $payload['new_roles'] === ['ROLE_ADMIN'];
            }));

        $service = new UsersService($repository, $manager, $roles, $kafka);
        $service->setNewRole(1, ['ROLE_ADMIN']);
    }
}
