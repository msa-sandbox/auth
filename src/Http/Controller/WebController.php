<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Response\ApiResponse;
use App\Security\Roles;
use App\Service\UsersService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/web')]
readonly class WebController
{
    public function __construct(
    ) {
    }

    #[Route('/users', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUsers(Request $request, UsersService $service): ApiResponse
    {
        return ApiResponse::success($service->getAllUsers());
    }

    #[Route('/users/{id}/roles', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setUserRole(Request $request, UsersService $service, int $id): ApiResponse
    {
        $data = $request->getPayload()->all();
        $newRoles = $data['roles'] ?? [];

        if (!$newRoles || array_diff($newRoles, Roles::ALL_ROLES)) {
            return ApiResponse::error('Invalid role provided');
        }

        $service->setNewRole($id, $newRoles);

        return ApiResponse::success(message: 'Roles updated');
    }

    #[Route('/users/roles', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserRoles(Request $request): ApiResponse
    {
        return ApiResponse::success(Roles::ALL_ROLES);
    }
}
