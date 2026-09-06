<?php

declare(strict_types=1);

namespace App\Reminder\Application\Query;

use App\Reminder\Domain\Repository\CalendarFeedRepository;
use App\Shared\Application\Tenant\CurrentTenant;

final readonly class CalendarFeedQuery
{
    public function __construct(
        private CalendarFeedRepository $feeds,
        private CurrentTenant $tenant,
    ) {
    }

    public function tokenOfCurrentOrganization(): ?string
    {
        return $this->feeds->ofOrganization($this->tenant->id())?->token()->toString();
    }
}
