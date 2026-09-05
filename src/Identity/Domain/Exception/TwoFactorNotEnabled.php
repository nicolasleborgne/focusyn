<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use DomainException;

final class TwoFactorNotEnabled extends DomainException
{
    public static function create(): self
    {
        return new self("La double authentification n'est pas active sur ce compte.");
    }
}
