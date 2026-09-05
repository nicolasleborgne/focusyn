<?php

declare(strict_types=1);

namespace App\Task\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Intitulé d'une tâche.
 *
 * Les espaces surnuméraires sont réduits : une tâche saisie sur plusieurs
 * lignes par un copier-coller resterait sinon illisible dans une liste à
 * cocher.
 */
final readonly class TaskText implements Stringable
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
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Une tâche doit avoir un intitulé.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Un intitulé de tâche dépasse %d caractères ; c\'est une note, pas une tâche.', self::MAX_LENGTH));
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
