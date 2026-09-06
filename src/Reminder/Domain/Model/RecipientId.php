<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use App\Shared\Domain\EntityId;

/**
 * La personne à prévenir, du point de vue de Reminder.
 *
 * Porte la même valeur que le `UserId` d'Identity sans être le même type :
 * Reminder n'a pas le droit de connaître Identity. L'adresse, elle, n'est pas
 * recopiée — elle est résolue à l'envoi par le port `AccountDirectory`, sans
 * quoi un changement d'adresse laisserait les anciens rappels partir vers
 * l'ancienne.
 */
final readonly class RecipientId extends EntityId
{
}
