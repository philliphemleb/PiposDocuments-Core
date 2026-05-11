<?php

declare(strict_types=1);

namespace App\Infrastructure\Health\Controller;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[OA\Get(
        path: '/api/health',
        summary: 'Health check',
        responses: [
            new OA\Response(response: 200, description: 'Service is healthy', content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'string', example: 'ok')])),
        ],
    )]
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
