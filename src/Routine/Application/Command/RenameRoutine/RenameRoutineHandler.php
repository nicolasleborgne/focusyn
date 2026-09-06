<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\RenameRoutine;

use App\Routine\Application\Exception\RoutineNotFound;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Model\RoutineName;
use App\Routine\Domain\Repository\RoutineRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RenameRoutineHandler
{
    public function __construct(
        private RoutineRepository $routines,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RenameRoutine $command): void
    {
        $routine = $this->routines->ofId(RoutineId::fromString($command->routineId))
            ?? throw RoutineNotFound::withId($command->routineId);

        $routine->rename(RoutineName::fromString($command->name), $this->clock->now());

        $this->routines->save($routine);
    }
}
