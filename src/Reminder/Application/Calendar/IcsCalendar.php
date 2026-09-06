<?php

declare(strict_types=1);

namespace App\Reminder\Application\Calendar;

use App\Reminder\Application\Query\ReminderView;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Rend des rappels au format iCalendar (RFC 5545).
 *
 * Le même rendu sert au téléchargement d'un événement et au flux auquel un
 * agenda s'abonne : deux portes, un seul texte, donc pas deux façons de dater
 * un même rappel.
 *
 * Les moments sont écrits en UTC (suffixe Z) : un agenda les replace dans le
 * fuseau du lecteur, ce qu'une heure locale sans fuseau ne permettrait pas.
 */
final readonly class IcsCalendar
{
    private const string PRODID = '-//Focusyn//Carnet//FR';
    private const int ALARM_MINUTES = 10;
    private const int DURATION_MINUTES = 30;

    /** @param list<ReminderView> $reminders */
    public function render(array $reminders, DateTimeImmutable $now, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($name),
        ];

        foreach ($reminders as $reminder) {
            $label = self::escape($reminder->label);

            array_push(
                $lines,
                'BEGIN:VEVENT',
                'UID:'.$reminder->id.'@focusyn',
                'DTSTAMP:'.self::utc($now),
                'DTSTART:'.self::utc($reminder->dueAt),
                'DURATION:PT'.self::DURATION_MINUTES.'M',
                'SUMMARY:'.$label,
                'DESCRIPTION:Focusyn',
                'BEGIN:VALARM',
                'TRIGGER:-PT'.self::ALARM_MINUTES.'M',
                'ACTION:DISPLAY',
                'DESCRIPTION:'.$label,
                'END:VALARM',
                'END:VEVENT',
            );
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Les caractères qui structurent le format ne peuvent pas apparaître tels
     * quels dans une valeur : un titre contenant une virgule couperait
     * l'événement en deux.
     */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", "\r", ';', ','],
            ['\\\\', '\\n', '', '\;', '\,'],
            $value,
        );
    }

    /**
     * La RFC limite une ligne à 75 octets ; au-delà, la suite est repliée
     * derrière une espace. Sans ce pliage, un titre long casse le fichier chez
     * les lecteurs stricts.
     */
    private static function fold(string $line): string
    {
        if (\strlen($line) <= 75) {
            return $line;
        }

        $folded = mb_strcut($line, 0, 75);
        $rest = substr($line, \strlen($folded));

        while ('' !== $rest) {
            $chunk = mb_strcut($rest, 0, 74);
            $folded .= "\r\n ".$chunk;
            $rest = substr($rest, \strlen($chunk));
        }

        return $folded;
    }
}
