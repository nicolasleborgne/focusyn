<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Shell;

use App\Routine\Application\Query\RoutineQuery;
use App\Routine\Application\Query\RoutineView;
use App\Shared\Application\Shell\RoutineSummary;
use App\Shared\Application\Shell\RoutineSummaryProvider;

/**
 * Frontière du contexte Routine : au-delà, plus personne ne manipule de
 * `RoutineView`.
 */
final readonly class RoutineShellSummary implements RoutineSummaryProvider
{
    public function __construct(
        private RoutineQuery $routines,
    ) {
    }

    public function routines(): array
    {
        return array_map(
            static fn (RoutineView $routine): RoutineSummary => new RoutineSummary(
                $routine->id,
                $routine->name,
                $routine->remaining,
            ),
            $this->routines->all(),
        );
    }
}
