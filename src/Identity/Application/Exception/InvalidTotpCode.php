<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use DomainException;

final class InvalidTotpCode extends DomainException
{
    public static function create(): self
    {
        return new self('Le code saisi ne correspond pas à celui attendu.');
    }
}
