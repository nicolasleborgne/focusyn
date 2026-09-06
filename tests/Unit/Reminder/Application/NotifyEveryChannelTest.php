<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reminder\Application;

use App\Reminder\Application\Notification\NotifyEveryChannel;
use App\Reminder\Application\Port\ReminderChannel;
use App\Reminder\Application\Query\ReminderView;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotifyEveryChannel::class)]
final class NotifyEveryChannelTest extends TestCase
{
    public function testEveryChannelIsUsed(): void
    {
        $mail = new SpyChannel();
        $push = new SpyChannel();

        (new NotifyEveryChannel([$mail, $push]))->notify($this->reminder(), 'compte', 'nicolas@focusyn.fr', 'fr');

        self::assertSame(1, $mail->calls);
        self::assertSame(1, $push->calls, 'Un canal muet n\'empêche pas les autres de parler.');
    }

    public function testWithoutAnyChannelNothingBreaks(): void
    {
        (new NotifyEveryChannel([]))->notify($this->reminder(), 'compte', 'nicolas@focusyn.fr', 'fr');

        $this->expectNotToPerformAssertions();
    }

    private function reminder(): ReminderView
    {
        return new ReminderView(
            id: '0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f',
            subject: 'note:0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f',
            label: 'Relire la synthèse',
            dueAt: new DateTimeImmutable('2026-09-10 09:00'),
            notified: false,
        );
    }
}

final class SpyChannel implements ReminderChannel
{
    public int $calls = 0;

    public function deliver(ReminderView $reminder, string $accountId, string $email, string $locale): bool
    {
        ++$this->calls;

        return false;
    }
}
