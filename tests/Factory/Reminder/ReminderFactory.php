<?php

declare(strict_types=1);

namespace App\Tests\Factory\Reminder;

use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Model\ReminderId;
use App\Reminder\Domain\Model\ReminderLabel;
use App\Reminder\Domain\Model\ReminderSubject;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Reminder> */
final class ReminderFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Reminder::class;
    }

    public function ownedBy(TenantId $tenant): static
    {
        return $this->with(['tenantId' => $tenant]);
    }

    public function forRecipient(RecipientId $recipient): static
    {
        return $this->with(['recipientId' => $recipient]);
    }

    public function about(string $label): static
    {
        return $this->with(['label' => ReminderLabel::fromString($label)]);
    }

    public function dueAt(string $moment): static
    {
        return $this->with(['dueAt' => new DateTimeImmutable($moment)]);
    }

    public function on(ReminderSubject $subject): static
    {
        return $this->with(['subject' => $subject]);
    }

    protected function defaults(): array
    {
        return [
            'id' => ReminderId::generate(),
            'tenantId' => TenantId::generate(),
            'recipientId' => RecipientId::generate(),
            'subject' => ReminderSubject::note(ReminderId::generate()->toString()),
            'label' => ReminderLabel::fromString(ucfirst(self::faker()->unique()->sentence(3))),
            'dueAt' => new DateTimeImmutable('2026-09-10 09:00'),
            'now' => new DateTimeImmutable('2026-09-05 12:00'),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(
            /** @param array{id: ReminderId, tenantId: TenantId, recipientId: RecipientId, subject: ReminderSubject, label: ReminderLabel, dueAt: DateTimeImmutable, now: DateTimeImmutable} $parameters */
            static fn (array $parameters): Reminder => Reminder::schedule(
                $parameters['id'],
                $parameters['tenantId'],
                $parameters['recipientId'],
                $parameters['subject'],
                $parameters['label'],
                $parameters['dueAt'],
                $parameters['now'],
            ),
        );
    }
}
