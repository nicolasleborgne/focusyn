<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Invitation;

use App\Organization\Application\Port\InvitationTokenGenerator;
use App\Organization\Domain\Model\InvitationToken;

final readonly class RandomInvitationTokenGenerator implements InvitationTokenGenerator
{
    public function generate(): InvitationToken
    {
        return InvitationToken::fromString(
            substr(rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '='), 0, 32),
        );
    }
}
