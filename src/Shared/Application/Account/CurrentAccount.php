<?php

declare(strict_types=1);

namespace App\Shared\Application\Account;

/**
 * Qui est connecté, vu depuis le noyau partagé.
 *
 * Déclaré ici et implémenté dans Identity : la coquille a besoin de l'adresse
 * du compte pour son pied de page, sans pour autant avoir le droit de connaître
 * les classes du contexte Identity.
 */
interface CurrentAccount
{
    public function isAuthenticated(): bool;

    public function emailOrNull(): ?string;

    public function idOrNull(): ?string;
}
