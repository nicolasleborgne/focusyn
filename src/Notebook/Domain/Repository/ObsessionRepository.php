<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Repository;

use App\Notebook\Domain\Model\Obsession;

interface ObsessionRepository
{
    public function save(Obsession $obsession): void;

    public function remove(Obsession $obsession): void;

    /**
     * Fiche d'une obsession, si elle a été écrite.
     */
    public function ofSlug(string $slug): ?Obsession;
}
