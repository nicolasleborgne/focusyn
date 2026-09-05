<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;
use Stringable;
use Symfony\Component\Uid\Uuid;

/**
 * Identifiant d'entité, typé par agrégat.
 *
 * Chaque agrégat déclare sa propre sous-classe (`NoteId`, `TaskListId`…) : le
 * compilateur refuse alors de passer un identifiant de note là où une tâche est
 * attendue, ce qu'une simple chaîne de caractères aurait laissé passer.
 *
 * Les valeurs sont des UUID v7 : ordonnés dans le temps, ils évitent la
 * fragmentation d'index que provoque un UUID v4 sur une clé primaire PostgreSQL,
 * tout en restant générables par le domaine — condition nécessaire pour qu'un
 * agrégat soit complet avant tout appel à la base.
 */
abstract readonly class EntityId implements Stringable
{
    final private function __construct(
        private Uuid $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function generate(): static
    {
        return new static(Uuid::v7());
    }

    public static function fromString(string $value): static
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException(\sprintf('"%s" n\'est pas un identifiant valide.', $value));
        }

        return new static(Uuid::fromString($value));
    }

    public function toString(): string
    {
        return $this->value->toRfc4122();
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class
            && $this->value->equals($other->value);
    }
}
