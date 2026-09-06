<?php

declare(strict_types=1);

namespace App\Routine\Domain\Model;

use InvalidArgumentException;
use Stringable;

/** Ce qu'il y a à refaire : une ligne, comme une tâche. */
final readonly class RoutineText implements Stringable
{
    private const int MAX_LENGTH = 240;

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
        $text = trim($value);

        if ('' === $text) {
            throw new InvalidArgumentException('Une ligne de routine n\'est pas vide.');
        }

        return new self(mb_substr($text, 0, self::MAX_LENGTH));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
