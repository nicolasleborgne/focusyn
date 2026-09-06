<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Invitation;

use App\Organization\Application\Port\InvitationLink;
use App\Organization\Domain\Model\Invitation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RoutedInvitationLink implements InvitationLink
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function to(Invitation $invitation): string
    {
        return $this->urls->generate(
            'invitation_accept',
            ['token' => $invitation->token()->toString()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
