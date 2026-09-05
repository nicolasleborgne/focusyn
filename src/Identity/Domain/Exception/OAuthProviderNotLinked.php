<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use DomainException;

final class OAuthProviderNotLinked extends DomainException
{
    public static function create(): self
    {
        return new self("Ce fournisseur n'est pas rattaché à ce compte.");
    }
}
