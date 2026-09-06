<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\DropReminder;

use App\Reminder\Domain\Model\ReminderSubject;
use App\Reminder\Domain\Repository\ReminderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DropReminderHandler
{
    public function __construct(
        private ReminderRepository $reminders,
    ) {
    }

    public function __invoke(DropReminder $command): void
    {
        $reminder = $this->reminders->ofSubject(ReminderSubject::fromString($command->subject));

        // Retirer un rappel absent n'est pas une erreur : le bouton n'apparaît
        // que s'il y en a un, mais deux onglets peuvent le cliquer.
        if (null !== $reminder) {
            $this->reminders->remove($reminder);
        }
    }
}
