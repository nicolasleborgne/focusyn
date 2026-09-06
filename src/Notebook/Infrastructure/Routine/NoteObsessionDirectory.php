<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Routine;

use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Shared\Application\Notebook\ObsessionDirectory;
use InvalidArgumentException;

final readonly class NoteObsessionDirectory implements ObsessionDirectory
{
    public function __construct(
        private NoteRepository $notes,
    ) {
    }

    public function slugOf(string $name): ?string
    {
        try {
            $obsession = ObsessionName::fromString($name);
        } catch (InvalidArgumentException) {
            return null;
        }

        // Mentionnée nulle part : l'écran existerait, mais vide. Mieux vaut ne
        // pas y conduire.
        return [] === $this->notes->taggedWith($obsession, 1) ? null : $obsession->slug();
    }
}
