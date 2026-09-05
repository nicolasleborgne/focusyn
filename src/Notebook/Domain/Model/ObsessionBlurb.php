<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Une phrase, deux au plus, qui disent de quoi traite une obsession.
 *
 * Volontairement court : au-delà, ce n'est plus une accroche mais une note, et
 * les notes ont leur place ailleurs.
 */
final readonly class ObsessionBlurb implements Stringable
{
    private const int MAX_LENGTH = 220;

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
            throw new InvalidArgumentException('Une accroche vide n\'a pas lieu d\'être : laissez-la absente.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Une accroche dépasse %d caractères ; écrivez une note.', self::MAX_LENGTH));
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
