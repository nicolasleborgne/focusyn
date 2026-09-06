<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Repository;

use App\Reminder\Domain\Model\CalendarFeed;
use App\Reminder\Domain\Model\FeedToken;
use App\Shared\Domain\TenantId;

interface CalendarFeedRepository
{
    public function save(CalendarFeed $feed): void;

    /**
     * Retrouver un flux par son jeton, hors de toute organisation.
     *
     * C'est la seule requête de l'application qui ne soit pas cloisonnée, et
     * c'est assumé : elle est appelée sans session, et le jeton tient lieu
     * d'authentification.
     */
    public function ofToken(FeedToken $token): ?CalendarFeed;

    public function ofOrganization(TenantId $organizationId): ?CalendarFeed;
}
