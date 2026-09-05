<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Model\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<UserId> */
final class UserIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return UserId::class;
    }
}
