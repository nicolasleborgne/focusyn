<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\TenantId;

/** @extends EntityIdType<TenantId> */
final class TenantIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return TenantId::class;
    }
}
