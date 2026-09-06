<?php

declare(strict_types=1);

namespace App\Shared\Application\Team;

/**
 * Combien de membres compte l'organisation courante.
 *
 * L'équipe se facture au membre : Billing doit pouvoir les compter sans
 * connaître Organization.
 */
interface TeamSize
{
    public function size(): int;
}
