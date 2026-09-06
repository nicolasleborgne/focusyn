<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

/**
 * Ce que la coquille sait des routines : leur nom, et ce qu'il en reste
 * aujourd'hui. Déclaré ici et implémenté par Routine, comme les autres résumés.
 */
interface RoutineSummaryProvider
{
    /** @return list<RoutineSummary> */
    public function routines(): array;
}
