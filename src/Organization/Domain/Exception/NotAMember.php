<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

use DomainException;

final class NotAMember extends DomainException
{
    public static function create(): self
    {
        return new self("Cette personne ne fait pas partie de l'organisation.");
    }
}
