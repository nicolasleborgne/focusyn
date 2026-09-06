<?php

declare(strict_types=1);

namespace App\Shared\Application\Reminder;

use DateTimeImmutable;

/**
 * Poser une échéance depuis un autre contexte.
 *
 * Le sujet est une chaîne (`task:<uuid>`, `note:<uuid>`), comme partout dans
 * Reminder : l'appelant n'a pas à connaître ce qu'est un rappel, et un rappel
 * n'a pas à savoir ce qu'il porte.
 *
 * Reposer une échéance sur le même sujet la **déplace**, elle ne s'empile pas —
 * c'est la règle du contexte, et elle vaut aussi par cette porte.
 */
interface ReminderScheduler
{
    public function scheduleFor(string $subject, string $label, DateTimeImmutable $dueAt): void;
}
