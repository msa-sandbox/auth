<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Service\UsersService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/web')]
readonly class WebController
{
    public function __construct(
    ) {
    }

    #[Route('/users', methods: ['GET'])]
    //    #[IsGranted('ROLE_USER')]
    public function getUsers(Request $request, UsersService $service): JsonResponse
    {
        $data = $service->handle();

        return new JsonResponse($data);
    }
}
