<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;
use DateTimeImmutable;

/**
 * Lectures du carnet, façonnées pour l'affichage.
 *
 * Les gabarits ne reçoivent jamais d'agrégat : ils recevraient avec lui la
 * possibilité de le modifier, et la tentation de le faire depuis une vue.
 */
final readonly class NotebookQuery
{
    public function __construct(
        private NoteRepository $notes,
    ) {
    }

    /** @return list<NoteSummary> */
    public function recent(int $limit = 50): array
    {
        return array_map($this->summarise(...), $this->notes->mostRecent($limit));
    }

    /** @return list<NoteSummary> */
    public function taggedWith(string $slug): array
    {
        foreach ($this->notes->obsessionCounts() as $obsession) {
            if ($obsession['slug'] === $slug) {
                return array_map(
                    $this->summarise(...),
                    $this->notes->taggedWith(ObsessionName::fromString($obsession['name'])),
                );
            }
        }

        return [];
    }

    /** @return list<NoteSummary> */
    public function search(string $query): array
    {
        return array_map($this->summarise(...), $this->notes->matching($query));
    }

    public function note(NoteId $id): ?NoteView
    {
        $note = $this->notes->ofId($id);

        if (null === $note) {
            return null;
        }

        return new NoteView(
            id: $note->id()->toString(),
            title: $note->title()->toString(),
            body: $note->body()->toString(),
            obsessions: array_map(static fn ($name): string => $name->toString(), $note->obsessions()),
            updatedAt: $note->updatedAt(),
            wordCount: $note->body()->wordCount(),
            readingMinutes: $note->body()->readingMinutes(),
        );
    }

    /** @return list<ObsessionSummary> */
    public function obsessions(): array
    {
        return array_map(
            static fn (array $row): ObsessionSummary => new ObsessionSummary($row['name'], $row['slug'], $row['count']),
            $this->notes->obsessionCounts(),
        );
    }

    public function count(): int
    {
        return $this->notes->count();
    }

    /**
     * Mots écrits depuis une date.
     *
     * Le compte est recalculé à la volée : le nombre de mots n'est pas stocké,
     * et le corps d'une note peut changer à chaque frappe. Acceptable sur un
     * carnet personnel ; à remplacer par un compteur entretenu le jour où une
     * organisation en accumulera des milliers.
     */
    public function wordsWrittenSince(DateTimeImmutable $since): int
    {
        return array_sum(array_map(
            static fn (Note $note): int => $note->body()->wordCount(),
            $this->notes->updatedSince($since),
        ));
    }

    private function summarise(Note $note): NoteSummary
    {
        return new NoteSummary(
            id: $note->id()->toString(),
            title: $note->title()->toString(),
            excerpt: $note->body()->excerpt(),
            obsessions: array_map(static fn ($name): string => $name->toString(), $note->obsessions()),
            updatedAt: $note->updatedAt(),
            wordCount: $note->body()->wordCount(),
        );
    }
}
