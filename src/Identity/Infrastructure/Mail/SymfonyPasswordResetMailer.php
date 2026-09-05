<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mail;

use App\Identity\Application\Port\PasswordResetMailer;
use App\Identity\Domain\Model\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonyPasswordResetMailer implements PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function send(User $user, string $url): void
    {
        $this->mailer->send(
            new TemplatedEmail()
                ->from(new Address('bonjour@focusyn.fr', 'Focusyn'))
                ->to($user->email()->toString())
                ->subject($this->translator->trans('password_reset.email.subject'))
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context(['url' => $url]),
        );
    }
}
