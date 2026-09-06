<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

interface EmailChangeMailer
{
    /** Le lien de confirmation, envoyé à la **nouvelle** adresse. */
    public function sendConfirmation(string $newEmail, string $url): void;

    /**
     * L'avertissement, envoyé à **l'ancienne**.
     *
     * C'est le seul moyen pour le propriétaire légitime d'apprendre qu'on
     * essaie de déplacer son compte : sans lui, une session dérobée changerait
     * l'adresse sans que personne ne le sache.
     */
    public function warnPreviousAddress(string $previousEmail, string $newEmail): void;
}
