<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Search;

use App\Routine\Domain\Model\Routine;
use App\Routine\Domain\Repository\RoutineRepository;
use App\Shared\Application\Search\RoutineFinder;
use App\Shared\Application\Search\RoutineHit;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * On cherche dans le nom de la routine **et** dans ses étapes.
 *
 * Une routine nommée « Matin » ne dit rien de ce qu'elle contient : c'est
 * « rafraîchir le levain » qu'on aura en tête, pas le titre. Chercher l'un sans
 * l'autre laisserait donc la moitié des routines introuvables.
 *
 * Le nom qui répond ne rend qu'une seule ligne, même si toutes les étapes
 * correspondent aussi : une routine trouvée par son nom est *une* réponse, pas
 * autant que d'étapes.
 */
final readonly class RoutineStepFinder implements RoutineFinder
{
    public function __construct(
        private RoutineRepository $routines,
        private ClockInterface $clock,
    ) {
    }

    public function matching(string $query, int $limit = 20): array
    {
        $needle = mb_strtolower(trim($query));

        if ('' === $needle) {
            return [];
        }

        $now = $this->clock->now();
        $hits = [];

        foreach ($this->routines->all() as $routine) {
            foreach ($this->hitsOf($routine, $needle, $now) as $hit) {
                $hits[] = $hit;

                if (\count($hits) >= $limit) {
                    return $hits;
                }
            }
        }

        return $hits;
    }

    /** @return list<RoutineHit> */
    private function hitsOf(Routine $routine, string $needle, DateTimeImmutable $now): array
    {
        $name = $routine->name()->toString();

        if (str_contains(mb_strtolower($name), $needle)) {
            return [new RoutineHit($routine->id()->toString(), $name, $name, false, matchedName: true)];
        }

        $hits = [];

        foreach ($routine->items() as $item) {
            $text = $item->text()->toString();

            if (str_contains(mb_strtolower($text), $needle)) {
                $hits[] = new RoutineHit(
                    $routine->id()->toString(),
                    $name,
                    $text,
                    $routine->isTicked($item->id(), $now),
                );
            }
        }

        return $hits;
    }
}
