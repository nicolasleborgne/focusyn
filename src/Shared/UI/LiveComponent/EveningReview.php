<?php

declare(strict_types=1);

namespace App\Shared\UI\LiveComponent;

use App\Shared\Application\Home\TaskTeaser;
use App\Shared\Application\Reminder\ReminderScheduler;
use App\Shared\Application\Shell\RoutineSummary;
use App\Shared\Application\Shell\RoutineSummaryProvider;
use App\Shared\Application\Shell\TaskSummaryProvider;
use Psr\Clock\ClockInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Ce qu'a été la journée, et ce qu'on en reporte.
 *
 * Le seul écran qui regarde trois contextes à la fois — tâches, routines,
 * rappels. Il vit donc dans `Shared` et n'en connaît aucun : chacun lui parle
 * par son port, et la revue ne manipule que des primitives.
 *
 * Reporter pose une échéance à demain neuf heures sur chaque tâche restée
 * ouverte. Un rappel par tâche, sur son propre sujet : c'est ce qui permet de
 * n'en déplacer qu'une ensuite, sans défaire le report entier.
 */
#[AsLiveComponent(name: 'EveningReview', template: 'components/EveningReview.html.twig')]
final class EveningReview
{
    use DefaultActionTrait;

    /**
     * Combien de tâches on propose de reporter d'un geste.
     *
     * Cinq : au-delà, « reporter à demain » cesse d'être une décision et
     * devient un déménagement, qu'on ne relira pas demain matin.
     */
    private const int CARRY_LIMIT = 5;

    #[LiveProp]
    public string $notice = '';

    public function __construct(
        private readonly TaskSummaryProvider $tasks,
        private readonly RoutineSummaryProvider $routines,
        private readonly ReminderScheduler $reminders,
        private readonly ClockInterface $clock,
    ) {
    }

    public function completedTasks(): int
    {
        return $this->tasks->completedToday();
    }

    public function openTasks(): int
    {
        return $this->tasks->openTaskCount();
    }

    /** Les routines du jour : ce qui est fait, sur ce qui était dû. */
    public function routinesDone(): int
    {
        return array_sum(array_map(
            static fn (RoutineSummary $routine): int => $routine->doneToday,
            $this->routines->routines(),
        ));
    }

    public function routinesDue(): int
    {
        return array_sum(array_map(
            static fn (RoutineSummary $routine): int => $routine->dueToday,
            $this->routines->routines(),
        ));
    }

    /** @return list<TaskTeaser> */
    public function carry(): array
    {
        return $this->tasks->nextTasks(self::CARRY_LIMIT);
    }

    #[LiveAction]
    public function deferAll(): void
    {
        $carried = $this->carry();

        if ([] === $carried) {
            return;
        }

        $tomorrow = $this->clock->now()->modify('+1 day')->setTime(9, 0);

        foreach ($carried as $task) {
            $this->reminders->scheduleFor('task:'.$task->taskId, $task->text, $tomorrow);
        }

        $this->notice = 'review.deferred';
    }
}
