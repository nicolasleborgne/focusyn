<?php

declare(strict_types=1);

namespace App\Shared\Application\Tenant;

use App\Shared\Domain\TenantId;

/**
 * Exécute un traitement dans le cloisonnement d'une organisation donnée.
 *
 * Hors requête HTTP — commande console, worker, tâche planifiée — aucune
 * organisation n'est établie et le filtre ne laisse rien passer. Ce port permet
 * de dire explicitement « pour cette organisation-là », au lieu de désarmer le
 * cloisonnement et d'espérer que la requête soit correctement filtrée à la main.
 */
interface TenantScope
{
    public function runAs(TenantId $tenant, callable $work): mixed;
}
