<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reminder\Domain;

use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Model\Reminder;
use App\Reminder\Domain\Model\ReminderId;
use App\Reminder\Domain\Model\ReminderLabel;
use App\Reminder\Domain\Model\ReminderSubject;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reminder::class)]
final class ReminderTest extends TestCase
{
    public function testASubjectIsRememberedByKindAndIdentifier(): void
    {
        $subject = ReminderSubject::note('0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f');

        self::assertSame('note', $subject->kind()->value);
        self::assertSame('note:0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f', $subject->toString());
        self::assertTrue($subject->equals(ReminderSubject::fromString($subject->toString())));
    }

    public function testANoteAndATaskSharingAnIdentifierAreTwoSubjects(): void
    {
        $id = '0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f';

        self::assertFalse(ReminderSubject::note($id)->equals(ReminderSubject::task($id)));
    }

    public function testSchedulingRecordsWhatWasAsked(): void
    {
        $reminder = $this->schedule('2026-09-10 09:00');

        self::assertSame('Relire la synthèse Sommeil', $reminder->label()->toString());
        self::assertSame('2026-09-10 09:00', $reminder->dueAt()->format('Y-m-d H:i'));
        self::assertFalse($reminder->hasBeenNotified());
    }

    public function testAReminderIsDueOnceItsMomentHasPassed(): void
    {
        $reminder = $this->schedule('2026-09-10 09:00');

        self::assertFalse($reminder->isDue(new DateTimeImmutable('2026-09-10 08:59')));
        self::assertTrue($reminder->isDue(new DateTimeImmutable('2026-09-10 09:00')));
        self::assertTrue($reminder->isDue(new DateTimeImmutable('2026-09-11 09:00')));
    }

    public function testAReminderIsOnlyNotifiedOnce(): void
    {
        $reminder = $this->schedule('2026-09-10 09:00');
        $now = new DateTimeImmutable('2026-09-10 09:01');

        $reminder->markNotified($now);

        self::assertTrue($reminder->hasBeenNotified());
        self::assertFalse($reminder->isDue($now));
        self::assertSame('2026-09-10 09:01', $reminder->notifiedAt()?->format('Y-m-d H:i'));
    }

    public function testMovingAReminderMakesItPendingAgain(): void
    {
        $reminder = $this->schedule('2026-09-10 09:00');
        $reminder->markNotified(new DateTimeImmutable('2026-09-10 09:01'));

        // Reporter une échéance déjà passée, c'est en attendre une nouvelle
        // notification : sans cela, un report resterait lettre morte.
        $reminder->moveTo(new DateTimeImmutable('2026-09-12 09:00'), new DateTimeImmutable('2026-09-10 10:00'));

        self::assertFalse($reminder->hasBeenNotified());
        self::assertTrue($reminder->isDue(new DateTimeImmutable('2026-09-12 09:00')));
    }

    public function testAPastMomentIsAccepted(): void
    {
        // « Ce soir 20 h » cliqué à 21 h : le raccourci doit continuer de
        // fonctionner, et le rappel part aussitôt.
        $reminder = $this->schedule('2026-09-05 20:00');

        self::assertTrue($reminder->isDue(new DateTimeImmutable('2026-09-05 21:00')));
    }

    public function testALabelIsTrimmedAndNeverEmpty(): void
    {
        self::assertSame('Relire', ReminderLabel::fromString("  Relire\n")->toString());

        $this->expectExceptionMessage('Un rappel doit porter un intitulé.');
        ReminderLabel::fromString('   ');
    }

    public function testAnUnknownSubjectKindIsRefused(): void
    {
        $this->expectExceptionMessage('« facture » n\'est pas un sujet de rappel.');
        ReminderSubject::fromString('facture:0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f');
    }

    private function schedule(string $dueAt): Reminder
    {
        return Reminder::schedule(
            ReminderId::generate(),
            TenantId::generate(),
            RecipientId::generate(),
            ReminderSubject::note('0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f'),
            ReminderLabel::fromString('Relire la synthèse Sommeil'),
            new DateTimeImmutable($dueAt),
            new DateTimeImmutable('2026-09-05 12:00'),
        );
    }
}
