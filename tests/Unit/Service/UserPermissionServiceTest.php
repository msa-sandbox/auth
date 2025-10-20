<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Entity\UserPermission;
use App\Repository\UserPermissionRepositoryInterface;
use App\Service\UserPermissionService;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;

class UserPermissionServiceTest extends TestCase
{
    /**
     * Test basic permissions update with read-only access.
     */
    public function testUpdateCreatesPermissionsWithReadOnly(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->expects($this->once())
            ->method('deleteBy')
            ->with([
                'user' => $user,
                'scope' => 'crm',
            ])
            ->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function ($permission) use ($user) {
                if (!$permission instanceof UserPermission) {
                    return false;
                }

                return $permission->getUser() === $user
                    && 'crm' === $permission->getScope()
                    && 'web' === $permission->getAccess()
                    && in_array($permission->getEntity(), ['lead', 'contact'])
                    && $permission->getAction() === ['read'];
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test permissions with write access - should automatically add read permission.
     */
    public function testUpdateAppliesPermissionHierarchyForWrite(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => true],
                'permissions' => [
                    'lead' => ['read' => false, 'write' => true, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($permission) {
                if (!$permission instanceof UserPermission) {
                    return false;
                }

                // Verify that 'write' permission automatically includes 'read'
                $actions = $permission->getAction();
                sort($actions);

                return 'all' === $permission->getAccess()
                    && 'lead' === $permission->getEntity()
                    && $actions === ['read', 'write'];
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test permissions with delete access - should automatically add write and read permissions.
     */
    public function testUpdateAppliesPermissionHierarchyForDelete(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'contact' => ['read' => false, 'write' => false, 'delete' => true, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($permission) {
                if (!$permission instanceof UserPermission) {
                    return false;
                }

                // Verify that 'delete' permission automatically includes 'write' and 'read'
                $actions = $permission->getAction();
                sort($actions);

                return 'contact' === $permission->getEntity()
                    && $actions === ['delete', 'read', 'write'];
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test that entities without read permission are skipped entirely.
     */
    public function testUpdateSkipsEntitiesWithoutReadPermission(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => false, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        // Only one entity (contact) should be persisted, lead should be skipped
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($permission) {
                return $permission instanceof UserPermission
                    && 'contact' === $permission->getEntity();
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test that both web and api access resolves to 'all'.
     */
    public function testUpdateResolvesAccessTypeToAll(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => true],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($permission) {
                return $permission instanceof UserPermission
                    && 'all' === $permission->getAccess();
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test that only web access resolves to 'web'.
     */
    public function testUpdateResolvesAccessTypeToWeb(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($permission) {
                return $permission instanceof UserPermission
                    && 'web' === $permission->getAccess();
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test that only api access resolves to 'api'.
     */
    public function testUpdateResolvesAccessTypeToApi(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => false, 'api' => true],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($permission) {
                return $permission instanceof UserPermission
                    && 'api' === $permission->getAccess();
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test that old permissions are deleted before creating new ones.
     */
    public function testUpdateDeletesOldPermissionsBeforeCreatingNew(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->expects($this->once())
            ->method('deleteBy')
            ->with([
                'user' => $user,
                'scope' => 'crm',
            ])
            ->willReturn(5); // Simulate 5 old permissions deleted

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test update with multiple entities and mixed permissions.
     */
    public function testUpdateWithMultipleEntitiesAndMixedPermissions(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => true],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => true, 'delete' => false, 'import' => true, 'export' => true],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'deal' => ['read' => true, 'write' => true, 'delete' => true, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $persistedPermissions = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(3))
            ->method('persist')
            ->willReturnCallback(function ($permission) use (&$persistedPermissions) {
                $persistedPermissions[] = [
                    'entity' => $permission->getEntity(),
                    'actions' => $permission->getAction(),
                ];
            });

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);

        // Verify lead has read, write, import, export
        $leadPermission = array_filter($persistedPermissions, fn ($p) => 'lead' === $p['entity'])[0];
        $leadActions = $leadPermission['actions'];
        sort($leadActions);
        $this->assertSame(['export', 'import', 'read', 'write'], $leadActions);

        // Verify contact has only read
        $contactPermission = array_filter($persistedPermissions, fn ($p) => 'contact' === $p['entity'])[1];
        $this->assertSame(['read'], $contactPermission['actions']);

        // Verify deal has read, write, delete (hierarchy applied)
        $dealPermission = array_filter($persistedPermissions, fn ($p) => 'deal' === $p['entity'])[2];
        $dealActions = $dealPermission['actions'];
        sort($dealActions);
        $this->assertSame(['delete', 'read', 'write'], $dealActions);
    }

    /**
     * Test that non-CRM scopes are ignored.
     */
    public function testUpdateIgnoresNonCrmScopes(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'messenger' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'chat' => ['read' => true, 'write' => true, 'delete' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        // deleteBy should never be called for non-CRM scopes
        $permissionRepository->expects($this->never())->method('deleteBy');

        $em = $this->createMock(EntityManagerInterface::class);
        // persist should never be called for non-CRM scopes
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test permission hierarchy with already correct permissions.
     */
    public function testUpdatePermissionHierarchyWithAlreadyCorrectPermissions(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => true, 'delete' => true, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->method('deleteBy')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($permission) {
                if (!$permission instanceof UserPermission) {
                    return false;
                }

                // All three permissions should be present
                $actions = $permission->getAction();
                sort($actions);

                return $actions === ['delete', 'read', 'write'];
            }));

        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test update with both access flags false - should delete old permissions and not create new ones.
     */
    public function testUpdateWithBothAccessFlagsFalseDeletesPermissionsOnly(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => false, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => true, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->expects($this->once())
            ->method('deleteBy')
            ->with([
                'user' => $user,
                'scope' => 'crm',
            ])
            ->willReturn(3); // Simulate 3 old permissions deleted

        $em = $this->createMock(EntityManagerInterface::class);
        // No permissions should be persisted when both access flags are false
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new UserPermissionService($permissionRepository, $em);
        $service->update($user, $newPermissions);
    }

    /**
     * Test update throws exception when access is true but permissions array is empty.
     */
    public function testUpdateThrowsExceptionWhenAccessTrueButPermissionsEmpty(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [], // Empty permissions
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->expects($this->once())
            ->method('deleteBy')
            ->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush'); // Should throw before flush

        $service = new UserPermissionService($permissionRepository, $em);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot grant access without any permissions');
        $service->update($user, $newPermissions);
    }

    /**
     * Test update throws exception when access is true but no entity has read permission.
     */
    public function testUpdateThrowsExceptionWhenAccessTrueButNoReadPermissions(): void
    {
        $user = (new User())
            ->setId(1)
            ->setEmail('test@example.com')
            ->setName('Test User');

        $newPermissions = [
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => false, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => false, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ];

        $permissionRepository = $this->createMock(UserPermissionRepositoryInterface::class);
        $permissionRepository->expects($this->once())
            ->method('deleteBy')
            ->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush'); // Should throw before flush

        $service = new UserPermissionService($permissionRepository, $em);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('At least one entity must have read permission when access is granted');
        $service->update($user, $newPermissions);
    }
}
