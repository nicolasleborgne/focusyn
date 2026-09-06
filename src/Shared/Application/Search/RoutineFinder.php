<?php

declare(strict_types=1);

namespace App\Shared\Application\Search;

/**
 * Chercher dans les routines, depuis l'écran de recherche.
 *
 * Déclaré ici et implémenté par Routine, comme `TaskFinder` l'est par Task :
 * Notebook porte l'écran de recherche et n'a le droit de connaître ni l'un ni
 * l'autre.
 */
interface RoutineFinder
{
    /** @return list<RoutineHit> */
    public function matching(string $query, int $limit = 20): array;
}
