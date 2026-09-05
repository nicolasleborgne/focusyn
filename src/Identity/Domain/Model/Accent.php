<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

/**
 * Couleur d'accentuation de l'interface.
 *
 * Cinq teintes fixées par le design, pas un sélecteur libre : une couleur
 * choisie au hasard casserait les contrastes calculés pour ces cinq-là.
 */
enum Accent: string
{
    case Ink = 'ink';
    case Slate = 'slate';
    case Olive = 'olive';
    case Tobacco = 'tobacco';
    case Brick = 'brick';

    public function hex(): string
    {
        return match ($this) {
            self::Ink => '#1f1f1f',
            self::Slate => '#41586e',
            self::Olive => '#5a6b4a',
            self::Tobacco => '#7d5a3c',
            self::Brick => '#8c4a45',
        };
    }
}
