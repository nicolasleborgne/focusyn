<?php

declare(strict_types=1);

namespace App\Shared\Application\Notebook;

/**
 * L'adresse d'une obsession, à partir de son nom.
 *
 * Déclaré ici et implémenté par Notebook : une routine retient le **nom** de
 * l'obsession qu'elle sert, et n'a pas le droit de savoir comment on en dérive
 * un slug — c'est une règle de Notebook, qui peut changer sans prévenir.
 *
 * Rend `null` quand l'obsession n'est mentionnée par aucune note. Une obsession
 * n'est pas créée, elle est mentionnée : lier vers un écran vide promettrait
 * quelque chose qui n'existe pas encore.
 */
interface ObsessionDirectory
{
    public function slugOf(string $name): ?string;
}
