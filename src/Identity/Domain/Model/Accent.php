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

    /**
     * La même teinte, éclaircie pour le thème sombre.
     *
     * Les cinq teintes de jour sont trop foncées sur un fond noir : « encre »
     * y disparaîtrait tout à fait. Ce sont les jumelles dessinées par la
     * maquette, pas un éclaircissement calculé — un `color-mix` donnerait des
     * contrastes inégaux d'une teinte à l'autre.
     *
     * Ces valeurs ne servent qu'aux pastilles de l'écran des réglages, qui
     * portent une couleur en attribut : partout ailleurs, c'est `--fx-accent`
     * qui bascule seul.
     */
    public function nightHex(): string
    {
        return match ($this) {
            self::Ink => '#e2e1de',
            self::Slate => '#8aa8c4',
            self::Olive => '#a2b98b',
            self::Tobacco => '#c9a077',
            self::Brick => '#d4938b',
        };
    }
}
