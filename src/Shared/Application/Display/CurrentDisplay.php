<?php

declare(strict_types=1);

namespace App\Shared\Application\Display;

/**
 * Réglages d'affichage du compte connecté, en primitives.
 *
 * Déclaré dans le noyau partagé et implémenté par Identity : le gabarit de base
 * doit poser les attributs `data-fx-*` sans avoir le droit de connaître les
 * types d'Identity. Hors session, les valeurs d'origine du design s'appliquent.
 */
interface CurrentDisplay
{
    public function accent(): string;

    public function proseFont(): string;

    public function density(): string;

    public function markOpacity(): float;

    public function previewPane(): bool;

    /** `system`, `light` ou `dark` — le design system en tire les conséquences. */
    public function theme(): string;
}
