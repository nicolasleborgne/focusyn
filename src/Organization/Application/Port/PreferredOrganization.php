<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

/**
 * Retient l'organisation dans laquelle on veut travailler.
 *
 * Un port et non un accès direct à la session : le cas d'usage sait qu'il
 * exprime une préférence, pas où celle-ci est rangée. C'est aussi ce qui
 * empêche la couche Application de connaître l'adaptateur qui la lit.
 */
interface PreferredOrganization
{
    public function remember(string $organizationId): void;
}
