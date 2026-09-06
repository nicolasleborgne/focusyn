<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use DateTimeImmutable;

/**
 * L'abonnement d'un navigateur aux notifications poussées.
 *
 * **Il suit la personne, pas l'organisation** — comme les choix de
 * confidentialité. Un même navigateur ne se dédouble pas selon l'organisation
 * dans laquelle on travaille, et un rappel se notifie à qui l'a posé.
 *
 * Le point de réception fait l'identité de l'appareil : un navigateur qui
 * renouvelle ses clés garde la même adresse, et doit être mis à jour plutôt que
 * dupliqué.
 */
final class PushSubscription extends AggregateRoot
{
    private function __construct(
        private readonly PushSubscriptionId $id,
        private readonly RecipientId $subscriberId,
        private readonly PushEndpoint $endpoint,
        private PushKeys $keys,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $lastSeenAt,
    ) {
    }

    public static function register(
        PushSubscriptionId $id,
        RecipientId $subscriberId,
        PushEndpoint $endpoint,
        PushKeys $keys,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $subscriberId, $endpoint, $keys, $now, $now);
    }

    public function id(): PushSubscriptionId
    {
        return $this->id;
    }

    public function subscriberId(): RecipientId
    {
        return $this->subscriberId;
    }

    public function endpoint(): PushEndpoint
    {
        return $this->endpoint;
    }

    public function keys(): PushKeys
    {
        return $this->keys;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function seenAgain(PushKeys $keys, DateTimeImmutable $now): void
    {
        $this->keys = $keys;
        $this->lastSeenAt = $now;
    }
}
