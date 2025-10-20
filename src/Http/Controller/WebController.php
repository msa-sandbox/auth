<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Dto\SetUserPermissionsRequestDto;
use App\Http\Response\ApiResponse;
use App\Security\AuthenticatedUserProviderInterface;
use App\Security\Roles;
use App\Service\CrmTokenService;
use App\Service\UsersService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/web')]
readonly class WebController
{
    #[Route('/users', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUsers(Request $request, UsersService $service): ApiResponse
    {
        return ApiResponse::success($service->getAllUsers());
    }

    #[Route('/user/{id}/permissions', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getUserPermissions(Request $request, UsersService $service, int $id): ApiResponse
    {
        return ApiResponse::success($service->getUserPermissions($id));
    }

    #[Route('/user/{id}/permissions', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setUserPermissions(Request $request, ValidatorInterface $validator, UsersService $service, int $id): ApiResponse
    {
        $dto = new SetUserPermissionsRequestDto($request->getPayload()->all());

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return ApiResponse::error($messages, status: Response::HTTP_BAD_REQUEST);
        }

        $service->setNewPermissions($id, $dto->toArray());

        return ApiResponse::success(message: 'Permissions updated');
    }

    #[Route('/users/roles', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserRoles(Request $request): ApiResponse
    {
        return ApiResponse::success(Roles::ALL_ROLES);
    }

    #[Route('/user/{id}/token', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateCrmToken(
        RateLimiterFactory $crmTokenPerUserLimiter,
        CrmTokenService $crmTokenService,
        AuthenticatedUserProviderInterface $userProvider,
        int $id,
    ): ApiResponse {
        $currentUser = $userProvider->getCurrentUser();

        // Check if current user is requesting token for themselves or is an admin
        if ($currentUser->getId() !== $id && !$userProvider->isAdmin()) {
            return ApiResponse::error('Forbidden', status: Response::HTTP_FORBIDDEN);
        }

        // Rate limiting per user
        $limiter = $crmTokenPerUserLimiter->create((string) $id);
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            return ApiResponse::error('Too many token generation requests', status: Response::HTTP_TOO_MANY_REQUESTS);
        }

        $tokenDto = $crmTokenService->generateExchangeToken($id);

        return ApiResponse::success([
            'token' => $tokenDto->getToken(),
            'expires_at' => $tokenDto->getExpiresAt()->format(DATE_ATOM),
            'ttl' => $tokenDto->getTtl(),
        ]);
    }
}
