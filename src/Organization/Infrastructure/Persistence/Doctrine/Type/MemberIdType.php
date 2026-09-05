<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\MemberId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<MemberId> */
final class MemberIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return MemberId::class;
    }
}
