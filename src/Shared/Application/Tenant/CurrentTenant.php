<?php

declare(strict_types=1);

namespace App\Shared\Application\Tenant;

use App\Shared\Domain\TenantId;

/**
 * Organisation dans laquelle la requête courante travaille.
 *
 * Déclaré ici, implémenté par le contexte Organization : les contextes métier
 * ont besoin de savoir « pour quelle organisation », sans avoir le droit de
 * connaître les organisations elles-mêmes.
 */
interface CurrentTenant
{
    public function idOrNull(): ?TenantId;

    /**
     * @throws NoCurrentTenant quand aucune organisation n'est établie — ce qui
     *                         est un défaut de câblage, jamais un cas nominal
     */
    public function id(): TenantId;
}
