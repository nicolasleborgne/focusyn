<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use InvalidArgumentException;

final class InvalidEmailAddress extends InvalidArgumentException
{
    public static function from(string $candidate): self
    {
        return new self(\sprintf('"%s" n\'est pas une adresse électronique valide.', $candidate));
    }
}
