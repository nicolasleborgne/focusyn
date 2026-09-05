<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use InvalidArgumentException;

/**
 * Visibilité des marques markdown dans l'éditeur.
 *
 * Quatre paliers, pas un curseur : entre deux valeurs voisines la différence
 * est imperceptible, et aux extrêmes le réglage devient inutilisable —
 * marques invisibles ou plus voyantes que le texte.
 */
final readonly class MarkOpacity
{
    private const array STEPS = [0.0, 0.25, 0.45, 0.8];

    private function __construct(
        private float $value,
    ) {
    }

    public static function fromFloat(float $value): self
    {
        foreach (self::STEPS as $step) {
            if (abs($step - $value) < 0.001) {
                return new self($step);
            }
        }

        throw new InvalidArgumentException(\sprintf('L\'opacité des marques vaut %s.', implode(', ', array_map(static fn (float $step): string => (string) $step, self::STEPS))));
    }

    /** @return list<float> */
    public static function steps(): array
    {
        return self::STEPS;
    }

    public function toFloat(): float
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < 0.001;
    }
}
