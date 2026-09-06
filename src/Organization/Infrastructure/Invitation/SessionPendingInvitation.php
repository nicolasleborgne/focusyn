<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Invitation;

use App\Organization\Application\Port\PendingInvitation;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Le jeton attend en session le temps qu'on se connecte ou qu'on s'inscrive.
 *
 * `take()` le retire au passage : une invitation ne s'accepte qu'une fois, et
 * un jeton qui traînerait en session rejouerait à chaque connexion suivante.
 */
final readonly class SessionPendingInvitation implements PendingInvitation
{
    private const string KEY = 'invitation.pending';

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function remember(string $token): void
    {
        $this->requests->getSession()->set(self::KEY, $token);
    }

    public function take(): ?string
    {
        $session = $this->requests->getSession();
        $token = $session->get(self::KEY);
        $session->remove(self::KEY);

        return \is_string($token) ? $token : null;
    }
}
