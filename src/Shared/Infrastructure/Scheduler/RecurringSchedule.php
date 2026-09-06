<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduler;

use App\Shared\Application\Scheduler\RecurringTask;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
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
    /** @param iterable<RecurringTask> $tasks */
    public function __construct(
        private CacheInterface $cache,
        #[AutowireIterator('app.recurring_task')]
        private iterable $tasks,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $schedule = (new SymfonySchedule())
            // Rejoue les exécutions manquées après un redémarrage du worker.
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);

        foreach ($this->tasks as $task) {
            $schedule->add(RecurringMessage::every($task->frequency(), $task->message()));
        }

        return $schedule;
    }
}
