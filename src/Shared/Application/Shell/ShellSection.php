<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

/**
 * Les grandes destinations de la coquille applicative.
 *
 * Sert à marquer l'entrée courante dans la barre latérale et la barre
 * d'onglets ; chaque écran déclare la section à laquelle il appartient.
 */
enum ShellSection: string
{
    case Home = 'home';
    case Library = 'library';
    case Tasks = 'tasks';
    case Search = 'search';
    case Settings = 'settings';
}
