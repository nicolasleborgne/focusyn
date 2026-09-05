<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine\Type;

use App\Notebook\Domain\Model\AuthorId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<AuthorId> */
final class AuthorIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return AuthorId::class;
    }
}
