<?php

declare(strict_types=1);

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\Subscription;
use App\Shared\Domain\TenantId;

/**
 * Aucune requête cloisonnée : les droits se lisent à chaque requête, parfois
 * avant qu'une organisation courante soit établie, et un webhook n'a pas de
 * session du tout. L'organisation est donc toujours passée explicitement.
 */
interface SubscriptionRepository
{
    public function save(Subscription $subscription): void;

    public function ofOrganization(TenantId $organizationId): ?Subscription;

    public function ofSubscriptionReference(string $reference): ?Subscription;

    public function ofCustomerReference(string $reference): ?Subscription;
}
