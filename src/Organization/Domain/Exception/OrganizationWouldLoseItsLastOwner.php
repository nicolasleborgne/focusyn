<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

use DomainException;

final class OrganizationWouldLoseItsLastOwner extends DomainException
{
    public static function create(): self
    {
        return new self('Une organisation doit toujours conserver au moins un propriétaire.');
    }
}
