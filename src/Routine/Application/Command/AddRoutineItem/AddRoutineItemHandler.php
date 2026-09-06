<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\AddRoutineItem;

use App\Routine\Application\Exception\RoutineNotFound;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Model\RoutineItemId;
use App\Routine\Domain\Model\RoutineText;
use App\Routine\Domain\Repository\RoutineRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddRoutineItemHandler
{
    public function __construct(
        private RoutineRepository $routines,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddRoutineItem $command): void
    {
        $routine = $this->routines->ofId(RoutineId::fromString($command->routineId))
            ?? throw RoutineNotFound::withId($command->routineId);

        $routine->addItem(RoutineItemId::generate(), RoutineText::fromString($command->text), $this->clock->now());

        $this->routines->save($routine);
    }
}
