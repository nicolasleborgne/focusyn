<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

use App\Organization\Domain\Model\OrganizationRole;
use DateTimeImmutable;

final readonly class InvitationEntry
{
    public function __construct(
        public string $id,
        public string $email,
        public OrganizationRole $role,
        public DateTimeImmutable $expiresAt,
        public bool $expired,
    ) {
    }
}
