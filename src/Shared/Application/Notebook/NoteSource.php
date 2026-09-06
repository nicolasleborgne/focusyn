<?php

declare(strict_types=1);

namespace App\Shared\Application\Notebook;

/**
 * Le texte d'une note, tel qu'on l'enverrait à un tiers.
 *
 * Déclaré ici et implémenté dans Notebook : l'assistant a besoin de lire une
 * note sans avoir le droit de connaître `Note`, `NoteBody` ni le dépôt qui les
 * porte. Le cloisonnement s'applique comme partout — une note d'une autre
 * organisation revient simplement absente.
 */
interface NoteSource
{
    public function textOf(string $noteId): ?string;
}
