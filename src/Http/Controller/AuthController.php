<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Dto\LoginRequestDto;
use App\Http\Response\ApiResponse;
use App\Security\AuthService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class AuthController
{
    public function __construct(
        private RateLimiterFactory $loginPerIpLimiter,
        private RateLimiterFactory $loginPerUserLimiter,
        private RateLimiterFactory $refreshPerIpLimiter,
    ) {
    }

    #[Route('/web/login', methods: ['POST'])]
    #[isGranted('PUBLIC_ACCESS')]
    public function login(Request $request, ValidatorInterface $validator, AuthService $authService): JsonResponse|ApiResponse
    {
        $dto = new LoginRequestDto(
            email: $request->get('email'),
            password: $request->get('password')
        );

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return ApiResponse::error($messages, status: Response::HTTP_BAD_REQUEST);
        }

        // Rate limit per IP
        $ipLimiter = $this->loginPerIpLimiter->create($request->getClientIp() ?? 'unknown');
        $ipLimit = $ipLimiter->consume();
        if (!$ipLimit->isAccepted()) {
            return ApiResponse::error('Too many requests from this IP', status: Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Rate limit per user (email)
        $userLimiter = $this->loginPerUserLimiter->create(strtolower($dto->getEmail()));
        $userLimit = $userLimiter->consume();
        if (!$userLimit->isAccepted()) {
            return ApiResponse::error('Too many login attempts for this account', status: Response::HTTP_TOO_MANY_REQUESTS);
        }

        $authDto = $authService->login($dto->getEmail(), $dto->getPassword());

        // Return refreshId within HttpOnly cookie
        $cookie = Cookie::create('refresh_id')
            ->withValue($authDto->getRefreshId())
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withSameSite('None')
            ->withPath('/web')
            ->withExpires($authDto->getExpiresAt());

        $response = (new JsonResponse(['token' => $authDto->getAccessToken()]));
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/web/refresh', methods: ['POST'])]
    #[isGranted('PUBLIC_ACCESS')]
    public function refresh(Request $request, AuthService $authService): JsonResponse|ApiResponse
    {
        // Rate limit per IP
        $ipLimiter = $this->refreshPerIpLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $ipLimiter->consume();
        if (!$limit->isAccepted()) {
            return ApiResponse::error('Too many refresh requests', status: Response::HTTP_TOO_MANY_REQUESTS);
        }

        $refreshId = $request->cookies->get('refresh_id');
        if (!$refreshId) {
            return ApiResponse::error('Missing refresh cookie', status: 401);
        }

        $authDto = $authService->refresh($refreshId);

        // Return refreshId within HttpOnly cookie
        $cookie = Cookie::create('refresh_id')
            ->withValue($authDto->getRefreshId())
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withSameSite('None')
            ->withPath('/web')
            ->withExpires($authDto->getExpiresAt());

        $response = (new JsonResponse(['token' => $authDto->getAccessToken()]));
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/web/logout', methods: ['POST'])]
    #[isGranted('PUBLIC_ACCESS')]
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
            ->withSameSite('None')
            ->withPath('/web');

        $response = new JsonResponse(['success' => true]);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
