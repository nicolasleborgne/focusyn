<?php

declare(strict_types=1);

namespace App\Notebook\Application\Exception;

use App\Notebook\Domain\Model\NoteId;
use DomainException;

/**
 * Levée aussi bien quand la note n'existe pas que lorsqu'elle appartient à une
 * autre organisation : le filtre la rend invisible, et distinguer les deux cas
 * révélerait son existence.
 */
final class NoteNotFound extends DomainException
{
    public static function withId(NoteId $id): self
    {
        return new self(\sprintf('Aucune note pour l\'identifiant %s.', $id->toString()));
    }
}
