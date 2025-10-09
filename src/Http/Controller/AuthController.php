<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Response\ApiResponse;
use App\Security\AuthService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AuthController
{
    public function __construct(
    ) {
    }

    #[Route('/web/login', methods: ['POST'])]
    public function login(Request $request, AuthService $authService): JsonResponse|ApiResponse
    {
        $data = $request->toArray();
        if (!$data['email'] || !$data['password']) {
            return ApiResponse::error('Missing email or password');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ApiResponse::error('Invalid email format');
        }

        $authDto = $authService->login($data['email'], $data['password']);

        // Return refreshId within HttpOnly cookie
        $cookie = Cookie::create('refresh_id')
            ->withValue($authDto->getRefreshId())
            ->withHttpOnly(true)
            ->withSecure(true) # true for https
            ->withPath('/web')
            ->withExpires($authDto->getExpiresAt());

        $response = (new JsonResponse(['token' => $authDto->getAccessToken()]));
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/web/refresh', methods: ['POST'])]
    public function refresh(Request $request, AuthService $authService): JsonResponse|ApiResponse
    {
        $refreshId = $request->cookies->get('refresh_id');
        if (!$refreshId) {
            return ApiResponse::error('Missing refresh cookie', status: 401);
        }

        $authDto = $authService->refresh($refreshId);

        // Return refreshId within HttpOnly cookie
        $cookie = Cookie::create('refresh_id')
            ->withValue($authDto->getRefreshId())
            ->withHttpOnly(true)
            ->withSecure(true) # true for https
            ->withPath('/web')
            ->withExpires($authDto->getExpiresAt());

        $response = (new JsonResponse(['token' => $authDto->getAccessToken()]));
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/web/logout', methods: ['POST'])]
    public function logout(Request $request, AuthService $authService): JsonResponse|ApiResponse
    {
        $refreshId = $request->cookies->get('refresh_id');
        if (!$refreshId) {
            return ApiResponse::error('Missing refresh cookie', status: 401);
        }

        $authService->logout($refreshId);

        $cookie = Cookie::create('refresh_id')
            ->withValue('')
            ->withExpires(0)
            ->withHttpOnly(true)
            ->withPath('/web');

        $response = new JsonResponse(['success' => true]);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
