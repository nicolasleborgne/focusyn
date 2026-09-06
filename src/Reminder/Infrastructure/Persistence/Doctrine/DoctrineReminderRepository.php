<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine;

use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Model\ReminderId;
use App\Reminder\Domain\Model\ReminderSubject;
use App\Reminder\Domain\Repository\ReminderRepository;
use App\Shared\Application\Event\DomainEventBus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineReminderRepository implements ReminderRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DomainEventBus $events,
    ) {
    }

    public function save(Reminder $reminder): void
    {
        $this->entityManager->persist($reminder);
        $this->entityManager->flush();

        $this->events->publish(...$reminder->releaseEvents());
    }

    public function remove(Reminder $reminder): void
    {
        $this->entityManager->remove($reminder);
        $this->entityManager->flush();
    }

    public function ofId(ReminderId $id): ?Reminder
    {
        return $this->entityManager
            ->createQuery('SELECT r FROM '.Reminder::class.' r WHERE r.id = :id')
            ->setParameter('id', $id->toString())
            ->getOneOrNullResult();
    }

    public function ofSubject(ReminderSubject $subject): ?Reminder
    {
        return $this->entityManager
            ->createQuery('SELECT r FROM '.Reminder::class.' r WHERE r.subject = :subject')
            ->setParameter('subject', $subject->toString())
            ->getOneOrNullResult();
    }

    public function ofSubjects(array $subjects): array
    {
        if ([] === $subjects) {
            return [];
        }

        /** @var list<Reminder> $reminders */
        $reminders = $this->entityManager
            ->createQuery('SELECT r FROM '.Reminder::class.' r WHERE r.subject IN (:subjects)')
            ->setParameter('subjects', $subjects)
            ->getResult();

        $indexed = [];

        foreach ($reminders as $reminder) {
            $indexed[$reminder->subject()->toString()] = $reminder;
        }

        return $indexed;
    }

    public function all(): array
    {
        /** @var list<Reminder> $reminders */
        $reminders = $this->entityManager
            ->createQuery('SELECT r FROM '.Reminder::class.' r ORDER BY r.dueAt ASC')
            ->getResult();

        return $reminders;
    }

    public function dueEverywhere(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Reminder> $reminders */
        $reminders = $this->entityManager
            ->createQuery(
                'SELECT r FROM '.Reminder::class.' r'
                .' WHERE r.dueAt <= :now AND r.notifiedAt IS NULL'
                .' ORDER BY r.dueAt ASC',
            )
            ->setParameter('now', $now)
            ->setMaxResults($limit)
            ->getResult();

        return $reminders;
    }
}
