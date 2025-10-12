<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Roles;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RolesTest extends KernelTestCase
{
    private Roles $roles;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->roles = self::getContainer()->get(Roles::class);
    }

    #[DataProvider('collapseDataProvider')]
    public function testCollapseRoles(array $input, array $expected): void
    {
        $result = $this->roles->collapseRoles($input);

        sort($result);
        sort($expected);

        self::assertSame($expected, $result);
    }

    public static function collapseDataProvider(): iterable
    {
        yield 'admin covers user' => [
            ['ROLE_ADMIN', 'ROLE_USER'],
            ['ROLE_ADMIN'],
        ];

        yield 'api covers user' => [
            ['ROLE_API_USER', 'ROLE_USER'],
            ['ROLE_API_USER'],
        ];

        yield 'web covers user' => [
            ['ROLE_WEB_USER', 'ROLE_USER'],
            ['ROLE_WEB_USER'],
        ];

        yield 'independent roles' => [
            ['ROLE_API_USER', 'ROLE_WEB_USER'],
            ['ROLE_API_USER', 'ROLE_WEB_USER'],
        ];

        yield 'no hierarchy' => [
            ['ROLE_USER'],
            ['ROLE_USER'],
        ];

        yield 'duplicates' => [
            ['ROLE_USER', 'ROLE_USER'],
            ['ROLE_USER'],
        ];
    }
}
