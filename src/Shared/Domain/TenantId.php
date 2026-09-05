<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Organisation propriétaire d'une donnée métier.
 *
 * Placé dans le noyau partagé, et non dans le contexte Organization : la
 * tenancy traverse tous les contextes métier (Notebook, Task, Reminder…), qui
 * doivent pouvoir désigner leur propriétaire sans connaître les types
 * d'Organization. Ce dernier convertit son `OrganizationId` en `TenantId` à sa
 * frontière.
 */
final readonly class TenantId extends EntityId
{
}
