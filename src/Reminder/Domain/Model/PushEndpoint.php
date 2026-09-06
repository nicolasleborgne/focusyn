<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * L'adresse à laquelle le service de notification du navigateur écoute.
 *
 * Elle est choisie par le navigateur, pas par nous : on ne la valide donc que
 * sur ce qui est certain — c'est une adresse https, et elle est bornée en
 * longueur pour tenir dans une colonne indexée.
 */
final readonly class PushEndpoint implements Stringable
{
    private const int MAX_LENGTH = 500;

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
        $trimmed = trim($value);

        if (!str_starts_with($trimmed, 'https://') || mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Un point de réception doit être une adresse https.');
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
