<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\User;

/**
 * Lien de réinitialisation, signé et daté.
 *
 * Aucun jeton n'est stocké : le lien porte sa propre preuve. Il devient caduc
 * de deux façons — l'expiration inscrite dans la signature, et l'empreinte du
 * mot de passe courant, qui change dès qu'il est réinitialisé. Un lien déjà
 * utilisé ne vaut donc plus rien, sans qu'il faille l'invalider explicitement.
 */
interface PasswordResetLink
{
    public function urlFor(User $user): string;

    public function isValidFor(User $user, string $uri): bool;
}
