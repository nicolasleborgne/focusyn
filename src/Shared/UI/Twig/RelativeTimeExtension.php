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
        return [new TwigFilter('fx_ago', $this->ago(...))];
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

    private function say(string $key, ?int $count = null): string
    {
        return $this->translator->trans($key, null === $count ? [] : ['count' => $count]);
    }
}
