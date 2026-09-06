<?php

declare(strict_types=1);

namespace App\Notebook\UI\LiveComponent;

use App\Notebook\Application\Query\NotebookQuery;
use App\Notebook\Application\Query\NoteSummary;
use App\Shared\Application\Search\TaskFinder;
use App\Shared\Application\Search\TaskHit;
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
        private readonly TaskFinder $tasks,
    ) {
    }

    /**
     * Tout est listé au départ ; taper réduit.
     *
     * Un écran de recherche vide qui ne montre rien n'apprend rien : la
     * maquette y déroule le carnet entier, et le champ le resserre.
     *
     * @return list<NoteSummary>
     */
    public function results(): array
    {
        return '' === trim($this->query)
            ? $this->notebook->recent()
            : $this->notebook->search($this->query);
    }

    /**
     * L'écran annonce « notes · tâches · tags » : les tâches arrivent par un
     * port partagé, Notebook n'ayant pas le droit de connaître le contexte Task.
     *
     * @return list<TaskHit>
     */
    public function taskResults(): array
    {
        return '' === trim($this->query) ? [] : $this->tasks->matching($this->query);
    }
}
