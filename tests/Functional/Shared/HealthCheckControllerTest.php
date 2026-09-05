<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Shared\UI\Http\HealthCheckController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(HealthCheckController::class)]
final class HealthCheckControllerTest extends WebTestCase
{
    public function testItReportsTheApplicationAsHealthy(): void
    {
        $client = self::createClient();
        $client->request('GET', '/healthz');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['status' => 'ok', 'database' => 'up'], $payload);
    }

    public function testItIsReachableWithoutAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/healthz');

        self::assertSame(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            'La sonde est interrogée par Docker : elle doit rester hors du pare-feu applicatif.',
        );
    }
}
