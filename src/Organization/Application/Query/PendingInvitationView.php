<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

use App\Organization\Domain\Model\OrganizationRole;

/**
 * L'invitation telle que la voit celui qui vient de cliquer le lien, avant même
 * d'avoir un compte.
 *
 * Elle ne dit rien de l'organisation au-delà de son nom : le lien peut avoir
 * été transféré, et il ne doit rien apprendre à qui n'était pas invité.
 */
final readonly class PendingInvitationView
{
    public function __construct(
        public string $organizationName,
        public string $email,
        public OrganizationRole $role,
        public bool $stillValid,
    ) {
    }
}
