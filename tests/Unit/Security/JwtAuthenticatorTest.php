<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\JwtAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class JwtAuthenticatorTest extends KernelTestCase
{
    private MockObject $jwtManager;
    private JwtAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->authenticator = new JwtAuthenticator($this->jwtManager);
    }

    public function testSupportsWithBearerHeader(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer token']);

        self::assertTrue($this->authenticator->supports($request));
    }

    public function testSupportsExcludedLoginPath(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/web/login']);
        $request->server->set('REQUEST_URI', '/web/login');
        $request->server->set('PATH_INFO', '/web/login');

        self::assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticateValidToken(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer valid.jwt']);
        $this->jwtManager
            ->expects(self::once())
            ->method('parse')
            ->with('valid.jwt')
            ->willReturn(['id' => 123, 'username' => 'user@example.com']);

        $passport = $this->authenticator->authenticate($request);

        $badge = $passport->getBadge(UserBadge::class);

        self::assertNotNull($badge);
        self::assertSame('user@example.com', $badge->getUserIdentifier());
    }

    public function testAuthenticateInvalidTokenThrowsException(): void
    {
        $this->jwtManager
            ->method('parse')
            ->willThrowException(new RuntimeException('Invalid'));

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer bad.jwt']);

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateWithoutUsernameThrowsException(): void
    {
        $this->jwtManager
            ->method('parse')
            ->willReturn(['something_else' => 'value']);

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer jwt.without.username']);

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($request);
    }
}
