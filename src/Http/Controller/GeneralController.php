<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('PUBLIC_ACCESS')]
readonly class GeneralController
{
    #[Route('/', methods: ['GET'])]
    public function root(): JsonResponse
    {
        return new JsonResponse(['message' => 'Hi. Nothing here']);
    }

    // We are not going to expose any endpoint, but the browser will argue about span within metrics
    #[Route('/favicon.ico', name: 'app_favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT, [
            'Content-Type' => 'image/x-icon',
        ]);
    }
}
