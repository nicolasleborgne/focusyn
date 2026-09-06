<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\DeleteRoutine;

use App\Routine\Application\Exception\RoutineNotFound;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Repository\RoutineRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteRoutineHandler
{
    public function __construct(
        private RoutineRepository $routines,
    ) {
    }

    public function __invoke(DeleteRoutine $command): void
    {
        $routine = $this->routines->ofId(RoutineId::fromString($command->routineId))
            ?? throw RoutineNotFound::withId($command->routineId);

        // Les lignes et les cochages partent avec : une routine supprimée ne
        // laisse pas derrière elle la trace de ce qu'on y faisait.
        $this->routines->remove($routine);
    }
}
