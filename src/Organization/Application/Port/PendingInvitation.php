<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

/**
 * L'invitation qu'on est en train d'accepter, retenue le temps de se connecter
 * ou de créer un compte.
 *
 * Un port et non un accès direct à la session : le cas d'usage sait qu'il met
 * une invitation de côté, pas où elle est rangée.
 */
interface PendingInvitation
{
    public function remember(string $token): void;

    public function take(): ?string;
}
