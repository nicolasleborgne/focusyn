<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Ce qui se dégage d'une obsession : une affirmation, pas un paragraphe.
 */
final readonly class ObsessionPoint implements Stringable
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
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Un point sans texte ne dégage rien.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Un point dépasse %d caractères ; il en faudrait deux.', self::MAX_LENGTH));
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
