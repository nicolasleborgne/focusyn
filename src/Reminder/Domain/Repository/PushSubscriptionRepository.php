<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Repository;

use App\Reminder\Domain\Model\PushEndpoint;
use App\Reminder\Domain\Model\PushSubscription;
use App\Reminder\Domain\Model\RecipientId;

/**
 * Aucun cloisonnement par organisation : un abonnement suit la personne. Toutes
 * les requêtes portent donc un `RecipientId` ou un point de réception, jamais
 * rien d'implicite.
 */
interface PushSubscriptionRepository
{
    public function save(PushSubscription $subscription): void;

    public function remove(PushSubscription $subscription): void;

    public function ofEndpoint(PushEndpoint $endpoint): ?PushSubscription;

    /** @return list<PushSubscription> */
    public function ofSubscriber(RecipientId $subscriber): array;
}
