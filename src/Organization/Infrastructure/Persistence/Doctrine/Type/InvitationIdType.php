<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\InvitationId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<InvitationId> */
final class InvitationIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return InvitationId::class;
    }
}
