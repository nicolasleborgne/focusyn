<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

/**
 * Clair, sombre, ou ce que dit le système.
 *
 * `System` n'est pas l'absence de choix : c'est un choix à part entière, et le
 * seul qui suive l'appareil au fil de la journée. Le ramener à « clair » dès
 * qu'on a basculé une fois interdirait d'y revenir.
 *
 * Rien ici ne décide d'une couleur. La valeur est rendue en attribut
 * `data-fx-theme` sur `<html>` et c'est le design system qui tranche — y
 * compris pour `System`, où la feuille de style s'en remet à
 * `prefers-color-scheme`. Le serveur ignore la préférence de l'appareil, et
 * c'est heureux : elle changerait sans qu'aucune requête n'en avertisse.
 */
enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';
}
