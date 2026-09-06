<?php

declare(strict_types=1);

namespace App\Notebook\UI\LiveComponent;

use App\Notebook\Application\Query\NotebookQuery;
use App\Notebook\Application\Query\NoteSummary;
use App\Notebook\Application\Query\ObsessionSummary;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * La bibliothèque, filtrée au fil de la frappe.
 *
 * Tout est listé au départ : le champ *réduit* une liste déjà là, il ne
 * l'invoque pas. C'est ce que fait la maquette, et c'est ce qui distingue
 * « filtrer la bibliothèque » de « chercher dans toutes les notes ».
 */
#[AsLiveComponent(name: 'NoteLibrary', template: 'components/NoteLibrary.html.twig')]
final class NoteLibrary
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $obsession = '';

    public function __construct(
        private readonly NotebookQuery $notebook,
    ) {
    }

    /** @return list<NoteSummary> */
    public function notes(): array
    {
        $notes = '' === $this->obsession
            ? $this->notebook->recent()
            : $this->notebook->taggedWith($this->obsession);

        $needle = mb_strtolower(trim($this->query));

        if ('' === $needle) {
            return $notes;
        }

        // Le filtre porte sur ce que l'écran montre — titre, extrait,
        // étiquettes — et non sur le corps entier : on réduit une liste
        // visible, on ne cherche pas ailleurs.
        return array_values(array_filter(
            $notes,
            static fn (NoteSummary $note): bool => str_contains(
                mb_strtolower($note->title.' '.$note->excerpt.' '.implode(' ', $note->obsessions)),
                $needle,
            ),
        ));
    }

    /** @return list<ObsessionSummary> */
    public function obsessions(): array
    {
        return $this->notebook->obsessions();
    }
}
