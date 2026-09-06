<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\TickRoutineItem;

use App\Routine\Application\Exception\RoutineNotFound;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Model\RoutineItemId;
use App\Routine\Domain\Repository\RoutineRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class TickRoutineItemHandler
{
    public function __construct(
        private RoutineRepository $routines,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(TickRoutineItem $command): void
    {
        $routine = $this->routines->ofId(RoutineId::fromString($command->routineId))
            ?? throw RoutineNotFound::withId($command->routineId);

        $routine->tick(RoutineItemId::fromString($command->itemId), $this->clock->now());

        $this->routines->save($routine);
    }
}
