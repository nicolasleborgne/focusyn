<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

use DomainException;

final class AlreadyAMember extends DomainException
{
    public static function create(): self
    {
        return new self("Cette personne fait déjà partie de l'organisation.");
    }
}
