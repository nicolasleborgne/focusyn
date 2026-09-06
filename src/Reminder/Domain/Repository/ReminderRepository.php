<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Repository;

use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Model\ReminderId;
use App\Reminder\Domain\Model\ReminderSubject;
use DateTimeImmutable;

interface ReminderRepository
{
    public function save(Reminder $reminder): void;

    public function remove(Reminder $reminder): void;

    public function ofId(ReminderId $id): ?Reminder;

    public function ofSubject(ReminderSubject $subject): ?Reminder;

    /**
     * Les rappels d'un sujet donné, par nature — de quoi peindre toutes les
     * puces d'un écran en une requête plutôt qu'une par ligne.
     *
     * @param list<string> $subjects
     *
     * @return array<string, Reminder> indexés par sujet
     */
    public function ofSubjects(array $subjects): array;

    /** @return list<Reminder> */
    public function all(): array;

    /**
     * Les rappels échus et jamais notifiés, toutes organisations confondues.
     *
     * Appelé par le worker, hors requête HTTP : le filtre de cloisonnement est
     * alors désarmé, et c'est voulu — un planificateur doit voir tout le monde.
     *
     * @return list<Reminder>
     */
    public function dueEverywhere(DateTimeImmutable $now, int $limit): array;
}
