<?php

declare(strict_types=1);

namespace App\Shared\Application\Tenant;

use LogicException;

final class NoCurrentTenant extends LogicException
{
    public static function create(): self
    {
        return new self(
            'Aucune organisation courante : une écriture métier a été tentée hors de toute organisation.',
        );
    }
}
