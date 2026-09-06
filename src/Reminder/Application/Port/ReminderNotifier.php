<?php

declare(strict_types=1);

namespace App\Reminder\Application\Port;

use App\Reminder\Application\Query\ReminderView;

/**
 * Prévenir la personne d'une échéance arrivée.
 *
 * Un port et non un appel direct au courrielleur : une notification poussée
 * viendra s'ajouter, et le cas d'usage ne doit pas avoir à savoir combien de
 * canaux existent.
 */
interface ReminderNotifier
{
    public function notify(ReminderView $reminder, string $accountId, string $email, string $locale): void;
}
