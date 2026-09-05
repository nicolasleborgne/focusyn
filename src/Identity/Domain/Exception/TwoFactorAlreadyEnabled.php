<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use DomainException;

final class TwoFactorAlreadyEnabled extends DomainException
{
    public static function create(): self
    {
        return new self('La double authentification est déjà active sur ce compte.');
    }
}
