<?php

declare(strict_types=1);

namespace App\Routine\Application\Exception;

use RuntimeException;

final class RoutineNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Aucune routine « %s ».', $id));
    }
}
