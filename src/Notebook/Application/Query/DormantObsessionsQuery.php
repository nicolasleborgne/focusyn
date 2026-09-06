<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

use App\Notebook\Domain\Repository\NoteRepository;
use App\Shared\Application\Home\DormantObsession;
use Psr\Clock\ClockInterface;

/**
 * Les obsessions qu'on ne nourrit plus.
 *
 * **Six semaines sans une note**, et l'accueil le signale. Le seuil est un
 * choix, pas une mesure : assez long pour qu'un sujet saisonnier n'y tombe pas
 * à la première semaine creuse, assez court pour qu'on puisse encore reprendre
 * le fil.
 *
 * La maquette, elle, appelait « dormante » une obsession de moins de deux
 * notes — un raccourci de prototype, qui aurait signalé les obsessions
 * *neuves* au lieu de celles qu'on délaisse, exactement l'inverse de ce qu'on
 * cherche.
 */
final readonly class DormantObsessionsQuery
{
    private const int DORMANT_AFTER_WEEKS = 6;
    private const int LIMIT = 3;

    public function __construct(
        private NoteRepository $notes,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<DormantObsession> */
    public function all(): array
    {
        $now = $this->clock->now();
        $dormant = [];

        // Le dépôt rend la plus ancienne d'abord : les premières sont donc les
        // plus endormies, et l'on peut s'arrêter dès qu'on en a trois.
        foreach ($this->notes->obsessionLastMentions() as $obsession) {
            $weeks = intdiv($now->getTimestamp() - $obsession['lastMentionedAt']->getTimestamp(), 7 * 86400);

            if ($weeks < self::DORMANT_AFTER_WEEKS) {
                break;
            }

            $dormant[] = new DormantObsession($obsession['name'], $obsession['slug'], $weeks);

            if (self::LIMIT === \count($dormant)) {
                break;
            }
        }

        return $dormant;
    }
}
