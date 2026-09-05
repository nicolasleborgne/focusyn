<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use App\Shared\Domain\EntityId;

/**
 * Référence vers une personne, du point de vue d'Organization.
 *
 * Porte la même valeur que le `UserId` du contexte Identity, mais reste un
 * type distinct : Organization n'a pas le droit de connaître les classes
 * d'Identity, et n'a de toute façon pas besoin de savoir ce qu'est un compte.
 * Le pont entre les deux se fait par l'événement `UserWasRegistered`.
 */
final readonly class MemberId extends EntityId
{
}
