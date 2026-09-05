<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Model\OAuthIdentityId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;

/** @extends EntityIdType<OAuthIdentityId> */
final class OAuthIdentityIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return OAuthIdentityId::class;
    }
}
