<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

readonly class WebController
{
    public function __construct(
    ) {
    }

    #[Route('/users', methods: ['GET'])]
    public function getUsers(Request $request): JsonResponse
    {
        $data = ['alice'];

        return new JsonResponse($data);
    }
}
