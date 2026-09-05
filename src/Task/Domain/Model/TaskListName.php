<?php

declare(strict_types=1);

namespace App\Task\Domain\Model;

use InvalidArgumentException;
use Stringable;

final readonly class TaskListName implements Stringable
{
    private const int MAX_LENGTH = 120;

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
            throw new InvalidArgumentException('Une liste doit avoir un nom.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Un nom de liste dépasse %d caractères.', self::MAX_LENGTH));
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
