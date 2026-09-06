<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\OpenRoutine;

use App\Routine\Domain\Model\Cadence;
use App\Routine\Domain\Model\Routine;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Model\RoutineName;
use App\Routine\Domain\Repository\RoutineRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Une routine naît quotidienne et sans nom d'auteur : on la renomme sur place,
 * comme une liste de tâches. Demander un nom dans un dialogue avant d'avoir vu
 * l'écran ferait hésiter pour rien.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class OpenRoutineHandler
{
    public function __construct(
        private RoutineRepository $routines,
        private CurrentTenant $tenant,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenRoutine $command): RoutineId
    {
        $routine = Routine::open(
            RoutineId::generate(),
            $this->tenant->id(),
            RoutineName::fromString('' !== trim($command->name) ? $command->name : 'Nouvelle routine'),
            Cadence::tryFrom($command->cadence) ?? Cadence::Daily,
            $this->clock->now(),
        );

        $this->routines->save($routine);

        return $routine->id();
    }
}
