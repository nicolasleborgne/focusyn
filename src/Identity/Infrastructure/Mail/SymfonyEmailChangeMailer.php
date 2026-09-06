<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mail;

use App\Identity\Application\Port\EmailChangeMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonyEmailChangeMailer implements EmailChangeMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function sendConfirmation(string $newEmail, string $url): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address('bonjour@focusyn.fr', 'Focusyn'))
                ->to($newEmail)
                ->subject($this->translator->trans('email_change.confirm.subject'))
                ->htmlTemplate('emails/email_change_confirm.html.twig')
                ->context(['url' => $url]),
        );
    }

    public function warnPreviousAddress(string $previousEmail, string $newEmail): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address('bonjour@focusyn.fr', 'Focusyn'))
                ->to($previousEmail)
                ->subject($this->translator->trans('email_change.warn.subject'))
                ->htmlTemplate('emails/email_change_warn.html.twig')
                ->context(['newEmail' => $newEmail]),
        );
    }
}
