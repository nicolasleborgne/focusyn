<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Le secret qui ouvre un flux d'agenda.
 *
 * Un agenda ne sait pas se connecter : il récupère une adresse, périodiquement,
 * sans session ni formulaire. Le jeton *est* donc l'authentification, d'où sa
 * longueur — et d'où la possibilité de le renouveler, qui coupe l'accès à tous
 * les agendas déjà abonnés.
 */
final readonly class FeedToken implements Stringable
{
    private const int LENGTH = 43;

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
        if (1 !== preg_match('/^[A-Za-z0-9_-]{'.self::LENGTH.'}$/', $value)) {
            throw new InvalidArgumentException('Ce jeton de flux n\'a pas la forme attendue.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        // Comparaison à temps constant : c'est un secret, et un flux se devine
        // caractère par caractère si la comparaison s'arrête au premier écart.
        return hash_equals($this->value, $other->value);
    }
}
