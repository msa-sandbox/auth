<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Dto\ExchangeTokenRequestDto;
use App\Http\Dto\RefreshTokenRequestDto;
use App\Http\Response\ApiResponse;
use App\Service\CrmAuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[IsGranted('PUBLIC_ACCESS')]
readonly class ApiController
{
    public function __construct(
        private RateLimiterFactory $crmExchangePerIpLimiter,
        private RateLimiterFactory $crmRefreshPerIpLimiter,
    ) {
    }

    /**
     * Exchange a one-time exchange token for access and refresh JWT tokens.
     * Used by CRM to get initial authentication after user authorization.
     * Follows OAuth 2.0 token exchange pattern.
     */
    #[Route('/exchange', methods: ['POST'])]
    public function exchangeToken(
        Request $request,
        ValidatorInterface $validator,
        CrmAuthService $crmAuthService,
    ): ApiResponse {
        // Rate limiting per IP
        $limiter = $this->crmExchangePerIpLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            return ApiResponse::error('Too many exchange requests', status: Response::HTTP_TOO_MANY_REQUESTS);
        }

        $dto = new ExchangeTokenRequestDto(...$request->getPayload()->all());

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return ApiResponse::error($messages, status: Response::HTTP_BAD_REQUEST);
        }

        $authDto = $crmAuthService->exchangeToken($dto->getExchangeToken());

        return ApiResponse::success([
            'access_token' => $authDto->getAccessToken(),
            'refresh_token' => $authDto->getRefreshToken(),
            'token_type' => 'Bearer',
            'expires_in' => $authDto->getAccessTtl(),
            'refresh_expires_in' => $authDto->getRefreshTtl(),
        ]);
    }

    /**
     * Refresh access and refresh tokens using a valid refresh JWT.
     * Implements token rotation: old refresh token is revoked, new pair is issued.
     * Follows OAuth 2.0 refresh token grant type.
     */
    #[Route('/refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        ValidatorInterface $validator,
        CrmAuthService $crmAuthService,
    ): ApiResponse {
        // Rate limiting per IP
        $limiter = $this->crmRefreshPerIpLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            return ApiResponse::error('Too many refresh requests', status: Response::HTTP_TOO_MANY_REQUESTS);
        }

        $dto = new RefreshTokenRequestDto(...$request->getPayload()->all());

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return ApiResponse::error($messages, status: Response::HTTP_BAD_REQUEST);
        }

        $authDto = $crmAuthService->refreshTokens($dto->getRefreshToken());

        return ApiResponse::success([
            'access_token' => $authDto->getAccessToken(),
            'refresh_token' => $authDto->getRefreshToken(),
            'token_type' => 'Bearer',
            'expires_in' => $authDto->getAccessTtl(),
            'refresh_expires_in' => $authDto->getRefreshTtl(),
        ]);
    }
}
