<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine\Type;

use App\Notebook\Domain\Model\NoteId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<NoteId> */
final class NoteIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return NoteId::class;
    }
}
