<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Marque un agrégat comme appartenant à une organisation.
 *
 * Le filtre Doctrine n'ajoute sa clause qu'aux entités qui portent cette
 * interface : c'est ce qui distingue une note (cloisonnée) d'un compte
 * utilisateur (partagé entre organisations).
 */
interface TenantScoped
{
    public function tenantId(): TenantId;
}
