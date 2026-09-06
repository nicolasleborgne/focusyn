<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\Model\SubjectId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<SubjectId> */
final class SubjectIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return SubjectId::class;
    }
}
