<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Billing;

use App\Notebook\Domain\Repository\NoteRepository;
use App\Shared\Application\Notebook\NoteTally;

final readonly class StoredNoteTally implements NoteTally
{
    public function __construct(
        private NoteRepository $notes,
    ) {
    }

    public function count(): int
    {
        return $this->notes->count();
    }
}
