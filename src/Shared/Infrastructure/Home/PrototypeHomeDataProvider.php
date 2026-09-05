<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Home;

use App\Shared\Application\Home\HomeDataProvider;
use App\Shared\Application\Home\HomeView;
use App\Shared\Application\Home\NoteTeaser;
use App\Shared\Application\Home\TaskTeaser;
use Psr\Clock\ClockInterface;

/**
 * PROVISOIRE — extraits de la maquette (project/Focusyn.dc.html).
 *
 * À supprimer dès que les contextes Notebook et Task exposent leurs propres
 * requêtes. Contrairement à la maquette, les dates sont de vraies dates : le
 * prototype stockait « il y a 2 h » en dur, ce qui n'est pas transposable.
 */
final readonly class PrototypeHomeDataProvider implements HomeDataProvider
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function forCurrentUser(): HomeView
    {
        $now = $this->clock->now();

        return new HomeView(
            recentNotes: [
                new NoteTeaser(
                    title: "Le sommeil biphasique n'est pas une invention moderne",
                    excerpt: "Avant l'éclairage artificiel, la nuit se coupait en deux : premier sommeil, veille, second sommeil.",
                    obsessions: ['Sommeil', 'Histoire'],
                    updatedAt: $now->modify('-2 hours'),
                    wordCount: 214,
                ),
                new NoteTeaser(
                    title: "Extraction : pourquoi 1:16 n'est pas une loi",
                    excerpt: 'Le ratio ne décide de rien tout seul : mouture, temps et agitation font le goût.',
                    obsessions: ['Café'],
                    updatedAt: $now->modify('-1 day'),
                    wordCount: 96,
                ),
                new NoteTeaser(
                    title: 'Grille suisse : la marge est un argument',
                    excerpt: "Le blanc n'est pas du vide : c'est la structure rendue visible.",
                    obsessions: ['Typographie'],
                    updatedAt: $now->modify('-3 days'),
                    wordCount: 61,
                ),
            ],
            nextTasks: [
                new TaskTeaser('Écrire la synthèse Sommeil', 'Cette semaine', 'cette-semaine'),
                new TaskTeaser('Trier les 6 notes en friche', 'Cette semaine', 'cette-semaine'),
                new TaskTeaser('Deux semaines sans écran après 21 h', 'Protocole sommeil', 'protocole-sommeil'),
                new TaskTeaser("Noter l'heure du réveil spontané chaque matin", 'Protocole sommeil', 'protocole-sommeil'),
                new TaskTeaser('Mouture fixe, temps variable : 3 / 4 / 5 min', 'Essais café', 'essais-cafe'),
            ],
            monthlyWordCount: 2140,
        );
    }
}
