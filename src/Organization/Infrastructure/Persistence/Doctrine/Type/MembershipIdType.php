<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\MembershipId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<MembershipId> */
final class MembershipIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return MembershipId::class;
    }
}
