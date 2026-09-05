<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\OrganizationId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<OrganizationId> */
final class OrganizationIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return OrganizationId::class;
    }
}
