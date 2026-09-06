<?php

declare(strict_types=1);

namespace App\Reminder\Application\Query;

/**
 * Les rappels de l'organisation courante, chargés une fois par requête.
 *
 * Une puce est rendue par ligne de tâche et par note : interroger la base pour
 * chacune ferait dix requêtes là où il en faut une. Un carnet compte au plus un
 * rappel par sujet, l'ensemble tient en mémoire sans qu'on ait à s'en soucier.
 */
final class ReminderChips
{
    /** @var array<string, ReminderView>|null */
    private ?array $bySubject = null;

    public function __construct(
        private readonly ReminderQuery $reminders,
    ) {
    }

    public function of(string $subject): ?ReminderView
    {
        return $this->load()[$subject] ?? null;
    }

    /** @return array<string, ReminderView> */
    private function load(): array
    {
        if (null !== $this->bySubject) {
            return $this->bySubject;
        }

        $this->bySubject = [];

        foreach ($this->reminders->all() as $reminder) {
            $this->bySubject[$reminder->subject] = $reminder;
        }

        return $this->bySubject;
    }
}
