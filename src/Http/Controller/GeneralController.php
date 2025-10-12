<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

readonly class GeneralController
{
    #[Route('/', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['message' => 'Hi. Nothing here']);
    }
}
