<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Mail;

use App\Reminder\Application\Port\ReminderChannel;
use App\Reminder\Application\Query\ReminderView;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MailerReminderNotifier implements ReminderChannel
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function deliver(ReminderView $reminder, string $accountId, string $email, string $locale): bool
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

        return true;
    }
}
