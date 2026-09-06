<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;

/**
 * L'abonnement d'un agenda extérieur aux rappels d'une organisation.
 *
 * **Cet agrégat n'est délibérément pas `TenantScoped`.** Il est lu depuis une
 * requête sans session — un agenda ne se connecte pas — et le filtre, armé sans
 * organisation, ne laisserait rien passer. Le cloisonnement est ici assuré par
 * le jeton lui-même : on le retrouve, puis on lit les rappels *dans* son
 * organisation.
 */
final class CalendarFeed extends AggregateRoot
{
    private function __construct(
        private readonly CalendarFeedId $id,
        private readonly TenantId $organizationId,
        private FeedToken $token,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $rotatedAt,
    ) {
    }

    public static function open(
        CalendarFeedId $id,
        TenantId $organizationId,
        FeedToken $token,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $organizationId, $token, $now, $now);
    }

    public function id(): CalendarFeedId
    {
        return $this->id;
    }

    public function organizationId(): TenantId
    {
        return $this->organizationId;
    }

    public function token(): FeedToken
    {
        return $this->token;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rotatedAt(): DateTimeImmutable
    {
        return $this->rotatedAt;
    }

    /** Renouveler coupe l'accès à tous les agendas déjà abonnés : c'est le but. */
    public function rotate(FeedToken $token, DateTimeImmutable $now): void
    {
        $this->token = $token;
        $this->rotatedAt = $now;
    }
}
