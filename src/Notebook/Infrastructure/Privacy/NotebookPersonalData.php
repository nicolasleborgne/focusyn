<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Privacy;

use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Notebook\Domain\Repository\ObsessionRepository;
use App\Shared\Application\Privacy\PersonalDataContributor;

final readonly class NotebookPersonalData implements PersonalDataContributor
{
    private const int EVERYTHING = 10_000;

    public function __construct(
        private NoteRepository $notes,
        private ObsessionRepository $obsessions,
    ) {
    }

    public function section(): string
    {
        return 'notes';
    }

    public function export(): array
    {
        return array_map(
            static fn (Note $note): array => [
                'titre' => $note->title()->toString(),
                'corps' => $note->body()->toString(),
                'obsessions' => array_map(
                    static fn (ObsessionName $name): string => $name->toString(),
                    $note->obsessions(),
                ),
                'ecriteLe' => $note->writtenAt()->format(\DATE_ATOM),
                'modifieeLe' => $note->updatedAt()->format(\DATE_ATOM),
                'mots' => $note->body()->wordCount(),
            ],
            $this->notes->mostRecent(self::EVERYTHING),
        );
    }

    public function erase(): void
    {
        // Les obsessions se lisent depuis les notes : il faut les relever avant
        // de retirer celles-ci, sinon il ne resterait rien à relever.
        $slugs = array_column($this->notes->obsessionCounts(), 'slug');

        foreach ($this->notes->mostRecent(self::EVERYTHING) as $note) {
            $this->notes->remove($note);
        }

        // Les fiches d'obsession partent avec : elles ne décrivent plus rien.
        foreach ($slugs as $slug) {
            $record = $this->obsessions->ofSlug($slug);

            if (null !== $record) {
                $this->obsessions->remove($record);
            }
        }
    }
}
