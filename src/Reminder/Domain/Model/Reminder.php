<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Model;

use App\Reminder\Domain\Event\ReminderFellDue;
use App\Reminder\Domain\Event\ReminderWasScheduled;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TenantScoped;
use DateTimeImmutable;

/**
 * Une échéance posée sur une note ou une tâche.
 *
 * Un sujet ne porte qu'un rappel à la fois : reposer une échéance déplace
 * celle qui existe. C'est ce que fait la maquette, et c'est ce qui évite qu'un
 * clic répété sur la puce n'empile des notifications identiques.
 *
 * Une date déjà passée est acceptée : le raccourci « Ce soir 20 h » cliqué à
 * 21 h doit rester utilisable, et un rappel en retard mérite d'être notifié en
 * retard plutôt que jamais.
 */
final class Reminder extends AggregateRoot implements TenantScoped
{
    private function __construct(
        private readonly ReminderId $id,
        private readonly TenantId $tenantId,
        private readonly RecipientId $recipientId,
        private readonly ReminderSubject $subject,
        private ReminderLabel $label,
        private DateTimeImmutable $dueAt,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $notifiedAt,
    ) {
    }

    public static function schedule(
        ReminderId $id,
        TenantId $tenantId,
        RecipientId $recipientId,
        ReminderSubject $subject,
        ReminderLabel $label,
        DateTimeImmutable $dueAt,
        DateTimeImmutable $now,
    ): self {
        $reminder = new self($id, $tenantId, $recipientId, $subject, $label, $dueAt, $now, null);
        $reminder->recordThat(new ReminderWasScheduled(
            $id->toString(),
            $tenantId->toString(),
            $subject->toString(),
            $label->toString(),
            $dueAt,
            $now,
        ));

        return $reminder;
    }

    public function id(): ReminderId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function recipientId(): RecipientId
    {
        return $this->recipientId;
    }

    public function subject(): ReminderSubject
    {
        return $this->subject;
    }

    public function label(): ReminderLabel
    {
        return $this->label;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function notifiedAt(): ?DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function hasBeenNotified(): bool
    {
        return null !== $this->notifiedAt;
    }

    public function isDue(DateTimeImmutable $now): bool
    {
        return !$this->hasBeenNotified() && $this->dueAt <= $now;
    }

    /**
     * Reporter remet le rappel en attente : une échéance déjà notifiée qu'on
     * déplace doit repartir, sinon le report ne servirait à rien.
     */
    public function moveTo(DateTimeImmutable $dueAt, DateTimeImmutable $now): void
    {
        $this->dueAt = $dueAt;
        $this->notifiedAt = null;
        $this->recordThat(new ReminderWasScheduled(
            $this->id->toString(),
            $this->tenantId->toString(),
            $this->subject->toString(),
            $this->label->toString(),
            $dueAt,
            $now,
        ));
    }

    public function relabel(ReminderLabel $label): void
    {
        $this->label = $label;
    }

    public function markNotified(DateTimeImmutable $now): void
    {
        $this->notifiedAt = $now;
        $this->recordThat(new ReminderFellDue(
            $this->id->toString(),
            $this->tenantId->toString(),
            $this->subject->toString(),
            $this->label->toString(),
            $this->dueAt,
            $now,
        ));
    }
}
