<?php

declare(strict_types=1);

namespace App\Routine\Domain\Model;

use InvalidArgumentException;
use Stringable;

final readonly class RoutineName implements Stringable
{
    private const int MAX_LENGTH = 80;

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $name = trim($value);

        if ('' === $name) {
            throw new InvalidArgumentException('Une routine porte un nom.');
        }

        return new self(mb_substr($name, 0, self::MAX_LENGTH));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
