<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\NoteKeywords;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;

/**
 * Ce que la note en cours recoupe ailleurs dans le carnet.
 *
 * Une suggestion, pas un classement : on rapproche par les mots rares partagés,
 * et une obsession commune pèse autant que quatre mots — c'est le signal le
 * plus sûr dont on dispose, puisqu'il a été posé à la main.
 *
 * Le seuil écarte les rapprochements d'un ou deux mots, qui sont du bruit. Trois
 * résultats au plus : au-delà, la liste cesse d'être une remarque et devient un
 * écran de recherche, qui existe déjà et fait cela mieux.
 */
final readonly class RelatedNotesQuery
{
    private const int MIN_SCORE = 4;
    private const int OBSESSION_WEIGHT = 4;
    private const int LIMIT = 3;

    /**
     * Combien de notes on accepte de relire pour trouver ces trois-là.
     *
     * Le rapprochement se fait en mémoire : c'est tenable pour un carnet
     * personnel, et cela le resterait mal sur dix mille notes. La borne dit
     * jusqu'où l'on va, plutôt que de laisser l'écran ralentir sans prévenir.
     */
    private const int SCAN_LIMIT = 300;

    public function __construct(
        private NoteRepository $notes,
    ) {
    }

    /** @return list<RelatedNoteView> */
    public function of(NoteId $id): array
    {
        $note = $this->notes->ofId($id);

        if (null === $note) {
            return [];
        }

        $keywords = self::keywordsOf($note);
        $obsessions = array_map(static fn (ObsessionName $name): string => $name->slug(), $note->obsessions());

        $scored = [];

        foreach ($this->notes->mostRecent(self::SCAN_LIMIT) as $other) {
            if ($other->id()->equals($id)) {
                continue;
            }

            $shared = $keywords->shared(self::keywordsOf($other));
            $common = self::firstCommonObsession($other, $obsessions);
            $score = \count($shared) + (null === $common ? 0 : self::OBSESSION_WEIGHT);

            if ($score < self::MIN_SCORE) {
                continue;
            }

            $scored[] = [
                'score' => $score,
                'view' => new RelatedNoteView(
                    $other->id()->toString(),
                    $other->title()->toString(),
                    \array_slice($shared, 0, 3),
                    $common,
                ),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            static fn (array $entry): RelatedNoteView => $entry['view'],
            \array_slice($scored, 0, self::LIMIT),
        );
    }

    private static function keywordsOf(Note $note): NoteKeywords
    {
        return NoteKeywords::of($note->title()->toString().' '.$note->body()->toString());
    }

    /** @param list<string> $slugs */
    private static function firstCommonObsession(Note $note, array $slugs): ?string
    {
        foreach ($note->obsessions() as $obsession) {
            if (\in_array($obsession->slug(), $slugs, true)) {
                return $obsession->toString();
            }
        }

        return null;
    }
}
