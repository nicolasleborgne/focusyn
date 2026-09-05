<?php

declare(strict_types=1);

namespace App\Notebook\Application\Exception;

use DomainException;

final class ObsessionNotFound extends DomainException
{
    public static function withSlug(string $slug): self
    {
        return new self(\sprintf('Aucune obsession « %s » dans ce carnet.', $slug));
    }
}
