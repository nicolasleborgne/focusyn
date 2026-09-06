<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

use App\Organization\Domain\Model\Invitation;

interface InvitationMailer
{
    public function send(Invitation $invitation, string $organizationName, string $url): void;
}
