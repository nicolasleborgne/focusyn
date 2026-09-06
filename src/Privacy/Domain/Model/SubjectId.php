<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Model;

use App\Shared\Domain\EntityId;

/**
 * La personne concernée, au sens du RGPD.
 *
 * Porte la même valeur que le `UserId` d'Identity sans en être le type :
 * Privacy n'a pas à savoir ce qu'est un compte, seulement de qui sont les
 * données.
 */
final readonly class SubjectId extends EntityId
{
}
