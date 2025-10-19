<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Dto\SetUserPermissionsRequestDto;
use App\Http\Response\ApiResponse;
use App\Security\Roles;
use App\Service\UsersService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
}
