<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * L'intitulé d'un rappel, recopié depuis son sujet au moment où il est posé.
 *
 * Une copie plutôt qu'une lecture : la notification part d'un worker, souvent
 * bien après, et parfois pour un sujet entre-temps renommé ou supprimé. Le
 * rappel doit rester lisible seul.
 */
final readonly class ReminderLabel implements Stringable
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
            throw new InvalidArgumentException('Un rappel doit porter un intitulé.');
        }

        return new self(mb_substr($trimmed, 0, self::MAX_LENGTH));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
