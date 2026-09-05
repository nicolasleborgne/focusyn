<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use DomainException;

final class OAuthProviderAlreadyLinked extends DomainException
{
    public static function create(): self
    {
        return new self('Ce fournisseur est déjà rattaché à ce compte.');
    }
}
