<?php

declare(strict_types=1);

namespace App\Shared\Application\Account;

/**
 * De quoi joindre une personne à partir de son identifiant.
 *
 * Déclaré ici et implémenté dans Identity, comme `CurrentAccount` : un rappel
 * notifié par un worker doit pouvoir retrouver une adresse sans que Reminder
 * connaisse ce qu'est un compte.
 */
interface AccountDirectory
{
    public function emailOf(string $accountId): ?string;
}
