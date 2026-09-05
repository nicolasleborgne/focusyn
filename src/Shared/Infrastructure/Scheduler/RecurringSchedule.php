<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Planification récurrente de l'application.
 *
 * Les contextes n'inscrivent pas leurs tâches ici directement : ils exposent des
 * messages planifiés que ce fournisseur agrège, afin de garder un seul point
 * d'entrée pour le worker `messenger:consume scheduler_default`.
 */
#[AsSchedule]
final readonly class RecurringSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            // Rejoue les exécutions manquées après un redémarrage du worker.
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
