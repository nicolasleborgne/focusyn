<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Mail;

use App\Reminder\Application\Port\ReminderNotifier;
use App\Reminder\Application\Query\ReminderView;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MailerReminderNotifier implements ReminderNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function notify(ReminderView $reminder, string $email, string $locale): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address('bonjour@focusyn.fr', 'Focusyn'))
                ->to($email)
                ->subject($this->translator->trans(
                    'reminder.email.subject',
                    ['label' => $reminder->label],
                    locale: $locale,
                ))
                ->locale($locale)
                ->htmlTemplate('emails/reminder.html.twig')
                ->context(['reminder' => $reminder]),
        );
    }
}
