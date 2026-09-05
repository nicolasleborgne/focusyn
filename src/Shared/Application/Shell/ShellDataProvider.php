<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

/**
 * Port par lequel la coquille obtient de quoi se peindre.
 *
 * L'implémentation actuelle sert les données de la maquette ; elle sera
 * remplacée par une agrégation des contextes Notebook, Task et Identity sans
 * qu'aucun gabarit ne change.
 */
interface ShellDataProvider
{
    public function forCurrentUser(): ShellView;
}
