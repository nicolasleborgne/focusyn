<?php

declare(strict_types=1);

namespace App\Shared\Application\Search;

/**
 * Les tâches qui répondent à une recherche.
 *
 * Déclaré ici et implémenté dans Task : l'écran de recherche annonce
 * « notes · tâches · tags », et cette promesse ne peut pas être tenue sans un
 * pont — mais elle ne justifie pas que Notebook connaisse `TaskList`.
 */
interface TaskFinder
{
    /** @return list<TaskHit> */
    public function matching(string $query, int $limit = 20): array;
}
