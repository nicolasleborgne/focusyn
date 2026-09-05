<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use App\Shared\Domain\EntityId;

/**
 * Auteur d'une note, vu depuis Notebook.
 *
 * Porte la même valeur que le `UserId` d'Identity, sans en être le type :
 * Notebook n'a pas à savoir ce qu'est un compte, seulement qui a écrit.
 */
final readonly class AuthorId extends EntityId
{
}
