<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\NotifyDueReminders;

use App\Reminder\Application\Port\ReminderNotifier;
use App\Reminder\Application\Query\ReminderView;
use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Repository\ReminderRepository;
use App\Shared\Application\Account\AccountDirectory;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envoie ce qui est échu, une fois chacun.
 *
 * Un rappel en retard part quand même : après une interruption du worker, mieux
 * vaut prévenir tard que jamais. Il est marqué avant que le lot suivant ne soit
 * lu, de sorte qu'une seconde exécution ne le reprenne pas.
 *
 * Le cloisonnement est désarmé ici — c'est un traitement hors requête, qui doit
 * voir toutes les organisations. Chaque rappel porte la sienne.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class NotifyDueRemindersHandler
{
    private const int BATCH = 200;

    public function __construct(
        private ReminderRepository $reminders,
        private AccountDirectory $accounts,
        private ReminderNotifier $notifier,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private string $defaultLocale,
    ) {
    }

    public function __invoke(NotifyDueReminders $command): int
    {
        $now = $this->clock->now();
        $sent = 0;

        foreach ($this->reminders->dueEverywhere($now, self::BATCH) as $reminder) {
            $accountId = $reminder->recipientId()->toString();
            $email = $this->accounts->emailOf($accountId);

            if (null === $email) {
                // Le compte a disparu : le rappel n'a plus de destinataire. On
                // le marque quand même, sinon il serait relu à chaque tour.
                $this->logger->warning('Rappel sans destinataire joignable.', [
                    'reminder' => $reminder->id()->toString(),
                ]);
            } else {
                $this->notifier->notify(self::view($reminder), $accountId, $email, $this->defaultLocale);
                ++$sent;
            }

            $reminder->markNotified($now);
            $this->reminders->save($reminder);
        }

        return $sent;
    }

    private static function view(Reminder $reminder): ReminderView
    {
        return new ReminderView(
            id: $reminder->id()->toString(),
            subject: $reminder->subject()->toString(),
            label: $reminder->label()->toString(),
            dueAt: $reminder->dueAt(),
            notified: false,
        );
    }
}
