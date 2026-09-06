<?php

declare(strict_types=1);

namespace App\Shared\Application\Notebook;

/**
 * Combien de notes compte l'organisation courante.
 *
 * Déclaré ici et implémenté dans Notebook : l'écran d'abonnement doit dire où
 * l'on en est du plafond, sans avoir le droit de connaître ce qu'est une note.
 */
interface NoteTally
{
    public function count(): int;
}
