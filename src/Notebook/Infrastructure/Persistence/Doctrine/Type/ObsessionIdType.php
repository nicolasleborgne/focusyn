<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine\Type;

use App\Notebook\Domain\Model\ObsessionId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<ObsessionId> */
final class ObsessionIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return ObsessionId::class;
    }
}
