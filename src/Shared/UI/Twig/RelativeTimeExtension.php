<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * « il y a 2 h », « hier », « la semaine dernière ».
 *
 * La maquette date tout ainsi : dans un carnet, ce qui compte n'est pas la date
 * mais la distance. Les paliers sont choisis pour que la formule reste courte —
 * au-delà de deux mois, une date absolue en dit plus.
 *
 * Les libellés vivent dans le catalogue, comme le reste : `intl-icu` s'occupe
 * des pluriels de chaque langue.
 */
final class RelativeTimeExtension extends AbstractExtension
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('fx_ago', $this->ago(...)),
            new TwigFilter('fx_until', $this->until(...)),
        ];
    }

    public function ago(DateTimeImmutable $moment): string
    {
        $seconds = max(0, $this->clock->now()->getTimestamp() - $moment->getTimestamp());
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        $days = intdiv($hours, 24);

        return match (true) {
            $minutes < 1 => $this->say('time.now'),
            $hours < 1 => $this->say('time.minutes', $minutes),
            $days < 1 => $this->say('time.hours', $hours),
            1 === $days => $this->say('time.yesterday'),
            $days < 7 => $this->say('time.days', $days),
            $days < 14 => $this->say('time.last_week'),
            $days < 60 => $this->say('time.weeks', intdiv($days, 7)),
            default => $this->say('time.months', max(1, intdiv($days, 30))),
        };
    }

    /**
     * L'autre sens : « dans 12 jours », « demain ».
     *
     * Une échéance passée n'est pas rendue au futur — elle est dite passée,
     * ce qui est plus utile que « dans -3 jours ».
     */
    public function until(DateTimeImmutable $moment): string
    {
        $seconds = $moment->getTimestamp() - $this->clock->now()->getTimestamp();

        if ($seconds <= 0) {
            return $this->say('time.past');
        }

        $hours = intdiv($seconds, 3600);
        $days = intdiv($hours, 24);

        return match (true) {
            $hours < 1 => $this->say('time.in_minutes', max(1, intdiv($seconds, 60))),
            $days < 1 => $this->say('time.in_hours', $hours),
            1 === $days => $this->say('time.tomorrow'),
            $days < 31 => $this->say('time.in_days', $days),
            default => $this->say('time.in_months', max(1, intdiv($days, 30))),
        };
    }

    private function say(string $key, ?int $count = null): string
    {
        return $this->translator->trans($key, null === $count ? [] : ['count' => $count]);
    }
}
