<?php

declare(strict_types=1);

// Fournit le gestionnaire d'entités à l'extension Doctrine de PHPStan.

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

new Dotenv()->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel((string) $_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

/** @var ManagerRegistry $registry */
$registry = $kernel->getContainer()->get('doctrine');

return $registry->getManager();
