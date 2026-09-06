<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use InvalidArgumentException;
use Stringable;
use Symfony\Component\Uid\Uuid;

/**
 * Le sujet d'un rappel : une note ou une tâche, désignée par sa nature et son
 * identifiant.
 *
 * Les deux tiennent dans une seule colonne (`note:<uuid>`) parce que la seule
 * question qu'on leur pose est « quel rappel porte ce sujet ? » : une égalité
 * exacte, jamais un filtre par nature.
 */
final readonly class ReminderSubject implements Stringable
{
    private function __construct(
        private ReminderSubjectKind $kind,
        private string $id,
    ) {
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function note(string $id): self
    {
        return self::of(ReminderSubjectKind::Note, $id);
    }

    public static function task(string $id): self
    {
        return self::of(ReminderSubjectKind::Task, $id);
    }

    public static function fromString(string $value): self
    {
        [$kind, $id] = array_pad(explode(':', $value, 2), 2, '');
        $known = ReminderSubjectKind::tryFrom($kind)
            ?? throw new InvalidArgumentException(\sprintf('« %s » n\'est pas un sujet de rappel.', $kind));

        return self::of($known, $id);
    }

    public function kind(): ReminderSubjectKind
    {
        return $this->kind;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function toString(): string
    {
        return $this->kind->value.':'.$this->id;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->id === $other->id;
    }

    private static function of(ReminderSubjectKind $kind, string $id): self
    {
        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException(\sprintf('« %s » n\'est pas un identifiant de sujet valide.', $id));
        }

        return new self($kind, $id);
    }
}
