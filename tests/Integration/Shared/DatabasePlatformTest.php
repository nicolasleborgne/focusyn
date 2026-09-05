<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que la suite d'intégration parle bien à un PostgreSQL 17.
 *
 * Ce test paraît trivial ; il attrape en réalité les deux pannes les plus
 * coûteuses du harnais : une base de test pointant sur la base de développement,
 * et un `server_version` déclaré à tort, qui fait générer à Doctrine du SQL
 * incompatible sans jamais lever d'erreur explicite.
 */
final class DatabasePlatformTest extends KernelTestCase
{
    public function testItRunsOnPostgreSql(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        self::assertInstanceOf(PostgreSQLPlatform::class, $connection->getDatabasePlatform());
    }

    public function testItTargetsTheTestDatabase(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        self::assertSame(
            'focusyn_test',
            $connection->getDatabase(),
            'La suite d\'intégration ne doit jamais écrire dans la base de développement.',
        );
    }

    public function testTheDeclaredServerVersionMatchesTheRunningServer(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        $version = $connection->executeQuery('SHOW server_version')->fetchOne();
        self::assertIsString($version);

        self::assertStringStartsWith('17.', $version);
    }
}
