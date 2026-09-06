<?php

declare(strict_types=1);

namespace App\Shared\Application\Assistant;

use Closure;

/**
 * Faire écrire l'assistant, depuis un autre contexte.
 *
 * Déclaré ici et implémenté par Assistant : Notebook doit pouvoir demander une
 * synthèse sans connaître ni fournisseur, ni clé, ni consentement — et surtout
 * sans pouvoir contourner l'ordre dans lequel ces trois-là se vérifient.
 *
 * Le refus est une exception métier portant une clé de traduction : l'appelant
 * l'affiche, il n'a pas à savoir *pourquoi* on a refusé.
 */
interface WritingAssistant
{
    /**
     * La matière est **paresseuse** : elle n'est lue qu'une fois le
     * consentement et le palier vérifiés. Sans consentement, rien n'est lu —
     * et l'appelant n'a pas à connaître l'ordre des vérifications pour que
     * cette promesse tienne.
     *
     * @param string            $instruction ce qu'on demande
     * @param Closure(): string $source      la matière, ouverte au dernier moment
     *
     * @throws AssistantUnavailable consentement manquant, palier insuffisant,
     *                              assistant non configuré, clé indéchiffrable
     */
    public function complete(string $instruction, Closure $source): string;
}
