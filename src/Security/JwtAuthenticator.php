<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Throwable;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function supports(Request $request): bool
    {
        $path = $request->getPathInfo();

        // Exclude login route
        if (str_starts_with($path, '/web/login') || str_starts_with($path, '/web/refresh')) {
            return false;
        }

        $header = $request->headers->get('Authorization', '');

        // Check if token exists
        return str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        // Get token
        $authHeader = $request->headers->get('Authorization');
        $jwt = substr($authHeader, 7);

        // Validate & get payload
        try {
            $data = $this->jwtManager->parse($jwt);
        } catch (Throwable $e) {
            throw new AuthenticationException('Invalid JWT token');
        }

        // Ensure id and email exist in token
        if (!isset($data['user_id']) || !isset($data['username'])) {
            throw new AuthenticationException('Invalid JWT token: id or email not found');
        }

        // Get user through UserProvider
        // We still use only email for auth and ignore id. This is fine anyway.
        //  It is possible to change if profile callback into UserBadge.
        return new SelfValidatingPassport(
            new UserBadge($data['username'])
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Nothing, currently
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Invalid or expired JWT token'], Response::HTTP_UNAUTHORIZED);
    }
}
