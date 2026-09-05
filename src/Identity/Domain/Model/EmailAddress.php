<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Domain\Exception\InvalidEmailAddress;
use Stringable;

/**
 * Adresse électronique, normalisée à la construction.
 *
 * La normalisation (espaces retirés, casse abaissée) n'est pas cosmétique :
 * c'est elle qui garantit qu'« Nicolas@Focusyn.fr » et « nicolas@focusyn.fr »
 * ne peuvent pas donner deux comptes distincts.
 */
final readonly class EmailAddress implements Stringable
{
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
        $normalised = mb_strtolower(trim($value));

        if (false === filter_var($normalised, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmailAddress::from($value);
        }

        return new self($normalised);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        return substr($this->value, strrpos($this->value, '@') + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
