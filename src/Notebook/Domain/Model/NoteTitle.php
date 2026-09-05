<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use InvalidArgumentException;
use Stringable;

final readonly class NoteTitle implements Stringable
{
    private const int MAX_LENGTH = 200;

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
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Une note doit avoir un titre.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Un titre de note dépasse %d caractères.', self::MAX_LENGTH));
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
