<?php

declare(strict_types=1);

namespace App\Notebook\UI\LiveComponent;

use App\Notebook\Application\Query\NotebookQuery;
use App\Notebook\Application\Query\NoteSummary;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Recherche dans le carnet.
 *
 * Ici, un Live Component est à sa place : chaque frappe interroge réellement la
 * base, ce qu'aucun code client ne saurait faire sans dupliquer la requête.
 * Le débounce évite d'envoyer une requête par caractère.
 */
#[AsLiveComponent(name: 'NoteSearch', template: 'components/NoteSearch.html.twig')]
final class NoteSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    public function __construct(
        private readonly NotebookQuery $notebook,
    ) {
    }

    /** @return list<NoteSummary> */
    public function results(): array
    {
        return '' === trim($this->query) ? [] : $this->notebook->search($this->query);
    }

    public function hasSearched(): bool
    {
        return '' !== trim($this->query);
    }
}
