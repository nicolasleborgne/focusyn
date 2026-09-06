<?php

declare(strict_types=1);

namespace App\Reminder\Application\Query;

use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Model\ReminderId;
use App\Reminder\Domain\Model\ReminderSubject;
use App\Reminder\Domain\Repository\ReminderRepository;

final readonly class ReminderQuery
{
    public function __construct(
        private ReminderRepository $reminders,
    ) {
    }

    public function forId(string $id): ?ReminderView
    {
        $reminder = $this->reminders->ofId(ReminderId::fromString($id));

        return null === $reminder ? null : self::view($reminder);
    }

    public function forSubject(string $subject): ?ReminderView
    {
        $reminder = $this->reminders->ofSubject(ReminderSubject::fromString($subject));

        return null === $reminder ? null : self::view($reminder);
    }

    /**
     * Toutes les puces d'un écran en une requête.
     *
     * @param list<string> $subjects
     *
     * @return array<string, ReminderView>
     */
    public function forSubjects(array $subjects): array
    {
        return array_map(self::view(...), $this->reminders->ofSubjects($subjects));
    }

    /** @return list<ReminderView> */
    public function all(): array
    {
        return array_map(self::view(...), $this->reminders->all());
    }

    private static function view(Reminder $reminder): ReminderView
    {
        return new ReminderView(
            id: $reminder->id()->toString(),
            subject: $reminder->subject()->toString(),
            label: $reminder->label()->toString(),
            dueAt: $reminder->dueAt(),
            notified: $reminder->hasBeenNotified(),
        );
    }
}
