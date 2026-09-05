<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Sonde de disponibilité interrogée par Docker et par l'hébergeur.
 *
 * Vérifie que le conteneur répond ET que la base est joignable : un conteneur
 * qui sert des 500 parce que PostgreSQL est tombé ne doit pas être déclaré sain.
 */
final class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route(path: '/healthz', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $database = 'up';
            $status = Response::HTTP_OK;
        } catch (Throwable) {
            $database = 'down';
            $status = Response::HTTP_SERVICE_UNAVAILABLE;
        }

        return new JsonResponse(
            ['status' => Response::HTTP_OK === $status ? 'ok' : 'degraded', 'database' => $database],
            $status,
        );
    }
}
