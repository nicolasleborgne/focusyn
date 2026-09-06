<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

use App\Organization\Domain\Model\Invitation;

/**
 * L'adresse à laquelle une invitation s'accepte.
 *
 * Un port parce que le courriel est envoyé depuis un cas d'usage, qui n'a pas à
 * connaître le routeur.
 */
interface InvitationLink
{
    public function to(Invitation $invitation): string;
}
