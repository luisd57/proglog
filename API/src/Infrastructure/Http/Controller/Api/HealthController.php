<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class HealthController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/health', name: 'api_health', methods: ['GET'])]
    public function health(EntityManagerInterface $entityManager): JsonResponse
    {
        $databaseOk = false;

        try {
            $entityManager->getConnection()->executeQuery('SELECT 1');
            $databaseOk = true;
        } catch (\Exception) {
            // Database unreachable
        }

        // Intentionally bypasses the ApiResponseTrait envelope so health check
        // probes get a plain status response.
        return new JsonResponse([
            'status' => $databaseOk ? 'ok' : 'unhealthy',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $databaseOk ? 200 : 503);
    }
}
