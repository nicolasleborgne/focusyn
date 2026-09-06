<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model;

use App\Shared\Domain\EntityId;

/**
 * À qui appartient la clé, du point de vue d'Assistant.
 *
 * Porte la même valeur que le `UserId` d'Identity sans en être le type. Les
 * réglages suivent la personne et non l'organisation : c'est sa clé, c'est sa
 * facture.
 */
final readonly class OwnerId extends EntityId
{
}
