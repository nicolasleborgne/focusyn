<?php

declare(strict_types=1);

namespace App\Routine\Domain\Repository;

use App\Routine\Domain\Model\Routine;
use App\Routine\Domain\Model\RoutineId;

interface RoutineRepository
{
    public function save(Routine $routine): void;

    public function remove(Routine $routine): void;

    public function ofId(RoutineId $id): ?Routine;

    /** @return list<Routine> dans l'ordre d'ouverture */
    public function all(): array;
}
