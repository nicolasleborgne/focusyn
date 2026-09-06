<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Mail;

use App\Organization\Application\Port\InvitationMailer;
use App\Organization\Domain\Model\Invitation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonyInvitationMailer implements InvitationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function send(Invitation $invitation, string $organizationName, string $url): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address('bonjour@focusyn.fr', 'Focusyn'))
                ->to($invitation->email()->toString())
                ->subject($this->translator->trans('team.email.subject', ['organization' => $organizationName]))
                ->htmlTemplate('emails/invitation.html.twig')
                ->context([
                    'organization' => $organizationName,
                    'url' => $url,
                    'expiresAt' => $invitation->expiresAt(),
                ]),
        );
    }
}
