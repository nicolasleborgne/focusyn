<?php

declare(strict_types=1);

namespace App\Routine\UI\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Le calendrier d'une étape, en une ligne lisible.
 *
 * Composé ici et non dans la vue : c'est une phrase, donc cela se traduit, et
 * la couche Application n'a pas à connaître de catalogue. Composé ici et non
 * dans le gabarit non plus : un `{% if %}` de sept branches par ligne de
 * routine rendrait le gabarit illisible pour un résultat de trois mots.
 */
final class ScheduleExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('routine_schedule', $this->label(...)),
        ];
    }

    /** @param list<int> $days jours ISO, 1 lundi à 7 dimanche */
    public function label(string $cadence, array $days, ?int $rank): string
    {
        if ('daily' === $cadence) {
            return $this->translator->trans('routine.schedule.daily');
        }

        if ([] === $days) {
            return $this->translator->trans('weekly' === $cadence ? 'routine.schedule.weekly' : 'routine.schedule.monthly');
        }

        sort($days);
        $named = implode(' · ', array_map(fn (int $day): string => $this->translator->trans('routine.day.'.$day), $days));

        if ('weekly' === $cadence) {
            return 7 === \count($days) ? $this->translator->trans('routine.schedule.every_day') : $named;
        }

        $ordinal = $this->translator->trans('routine.rank.'.(-1 === $rank ? 'last' : ($rank ?? 1)));

        return $ordinal.' '.$named;
    }
}
