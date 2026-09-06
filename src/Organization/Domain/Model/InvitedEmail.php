<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * L'adresse à qui l'invitation est faite.
 *
 * Un type distinct de l'`EmailAddress` d'Identity : on invite souvent quelqu'un
 * qui n'a pas encore de compte, et Organization n'a de toute façon pas le droit
 * de connaître les types de ce contexte.
 */
final readonly class InvitedEmail implements Stringable
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
            throw new InvalidArgumentException(\sprintf('« %s » n\'est pas une adresse.', trim($value)));
        }

        return new self($normalised);
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
