<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reminder\Application;

use App\Reminder\Application\Calendar\IcsCalendar;
use App\Reminder\Application\Query\ReminderView;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IcsCalendar::class)]
final class IcsCalendarTest extends TestCase
{
    public function testAnEmptyCalendarIsStillAValidOne(): void
    {
        $ics = $this->render([]);

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        self::assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }

    public function testAMomentIsWrittenInUtcSoTheReaderCanReplaceIt(): void
    {
        // 9 h à Paris en septembre, c'est 7 h UTC.
        $ics = $this->render([$this->reminder('Relire la synthèse')]);

        self::assertStringContainsString('DTSTART:20260910T070000Z', $ics);
    }

    public function testTheStructuringCharactersAreEscaped(): void
    {
        $ics = $this->render([$this->reminder('Relire, trier ; puis publier')]);

        self::assertStringContainsString('SUMMARY:Relire\\, trier \; puis publier', $ics);
    }

    public function testALineNeverExceedsSeventyFiveOctets(): void
    {
        $ics = $this->render([$this->reminder(str_repeat('épreuve ', 40))]);

        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line), $line);
        }
    }

    public function testAFoldedLineIsPutBackTogetherByTheReader(): void
    {
        $label = str_repeat('épreuve ', 40);
        $ics = $this->render([$this->reminder($label)]);

        // Le dépliage est la lecture inverse : retirer les CRLF suivis d'une
        // espace doit rendre le titre intact.
        $unfolded = str_replace("\r\n ", '', $ics);

        self::assertStringContainsString('SUMMARY:'.trim($label), $unfolded);
    }

    public function testAnAlarmPrecedesTheMoment(): void
    {
        $ics = $this->render([$this->reminder('Relire')]);

        self::assertStringContainsString("BEGIN:VALARM\r\nTRIGGER:-PT10M", $ics);
    }

    /** @param list<ReminderView> $reminders */
    private function render(array $reminders): string
    {
        return (new IcsCalendar())->render(
            $reminders,
            new DateTimeImmutable('2026-09-05 12:00', new DateTimeZone('Europe/Paris')),
            'Focusyn',
        );
    }

    private function reminder(string $label): ReminderView
    {
        return new ReminderView(
            id: '0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f',
            subject: 'note:0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f',
            label: $label,
            dueAt: new DateTimeImmutable('2026-09-10 09:00', new DateTimeZone('Europe/Paris')),
            notified: false,
        );
    }
}
