<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

/**
 * Rattachement d'une note à une obsession.
 *
 * Entité interne à l'agrégat Note. Elle existe pour que le rattachement soit
 * une ligne indexable — compter les notes par obsession et filtrer dessus se
 * ferait mal sur une colonne JSON.
 */
final class NoteObsession
{
    private readonly string $slug;

    public function __construct(
        private readonly Note $note,
        private readonly ObsessionName $name,
    ) {
        $this->slug = $name->slug();
    }

    public function note(): Note
    {
        return $this->note;
    }

    public function name(): ObsessionName
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }
}
