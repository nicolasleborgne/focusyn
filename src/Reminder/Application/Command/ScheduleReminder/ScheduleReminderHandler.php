<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\ScheduleReminder;

use App\Reminder\Application\Exception\InvalidReminderMoment;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Model\ReminderId;
use App\Reminder\Domain\Model\ReminderLabel;
use App\Reminder\Domain\Model\ReminderSubject;
use App\Reminder\Domain\Repository\ReminderRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Tenant\CurrentTenant;
use DateTimeImmutable;
use Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Poser une échéance sur un sujet qui en porte déjà la déplace.
 *
 * Un sujet, un rappel : c'est ce que fait la maquette, et c'est ce qui évite
 * qu'un clic répété sur la puce n'empile des notifications identiques.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ScheduleReminderHandler
{
    public function __construct(
        private ReminderRepository $reminders,
        private CurrentTenant $tenant,
        private CurrentAccount $account,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ScheduleReminder $command): ReminderId
    {
        $subject = ReminderSubject::fromString($command->subject);
        $label = ReminderLabel::fromString($command->label);
        $dueAt = self::momentOf($command->dueAt);
        $now = $this->clock->now();

        $reminder = $this->reminders->ofSubject($subject);

        if (null === $reminder) {
            $reminder = Reminder::schedule(
                ReminderId::generate(),
                $this->tenant->id(),
                RecipientId::fromString($this->account->idOrNull() ?? throw new InvalidReminderMoment('Aucun compte connecté.')),
                $subject,
                $label,
                $dueAt,
                $now,
            );
        } else {
            $reminder->relabel($label);
            $reminder->moveTo($dueAt, $now);
        }

        $this->reminders->save($reminder);

        return $reminder->id();
    }

    private static function momentOf(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidReminderMoment(\sprintf('« %s » n\'est pas un moment.', $value));
        }
    }
}
