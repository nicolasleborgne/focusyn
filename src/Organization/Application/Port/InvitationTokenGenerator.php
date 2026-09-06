<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

use App\Organization\Domain\Model\InvitationToken;

interface InvitationTokenGenerator
{
    public function generate(): InvitationToken;
}
