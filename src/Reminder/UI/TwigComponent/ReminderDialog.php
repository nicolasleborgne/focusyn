<?php

declare(strict_types=1);

namespace App\Reminder\UI\TwigComponent;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Le dialogue d'échéance, rendu une fois par écran.
 *
 * Un seul exemplaire plutôt qu'un par puce : le sujet en cours d'édition est un
 * état d'onglet, que Stimulus recopie depuis la puce cliquée. Rien ici ne
 * justifie un aller-retour serveur — l'enregistrement, lui, est un envoi de
 * formulaire ordinaire.
 */
#[AsTwigComponent(name: 'ReminderDialog', template: 'components/ReminderDialog.html.twig')]
final class ReminderDialog
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Les raccourcis de la maquette, calculés côté serveur : « Lundi 8 h » ne
     * veut pas dire la même chose selon le jour où on le lit.
     *
     * @return list<array{label: string, date: string, time: string}>
     */
    public function shortcuts(): array
    {
        $now = $this->clock->now();

        return [
            $this->shortcut('tonight', $now, '20:00'),
            $this->shortcut('tomorrow', $now->modify('+1 day'), '09:00'),
            $this->shortcut('three_days', $now->modify('+3 days'), '09:00'),
            $this->shortcut('monday', $now->modify('next monday'), '08:00'),
        ];
    }

    public function defaultDate(): string
    {
        return $this->clock->now()->modify('+1 day')->format('Y-m-d');
    }

    /** @return array{label: string, date: string, time: string} */
    private function shortcut(string $label, DateTimeImmutable $day, string $time): array
    {
        return ['label' => $label, 'date' => $day->format('Y-m-d'), 'time' => $time];
    }
}
