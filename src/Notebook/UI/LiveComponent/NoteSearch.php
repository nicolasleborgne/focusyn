<?php

declare(strict_types=1);

namespace App\Notebook\UI\LiveComponent;

use App\Notebook\Application\Query\NotebookQuery;
use App\Notebook\Application\Query\NoteSummary;
use App\Shared\Application\Search\RoutineFinder;
use App\Shared\Application\Search\RoutineHit;
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
        private readonly RoutineFinder $routines,
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
     * L'écran annonce ce qu'il fouille : les tâches et les routines arrivent
     * par des ports partagés, Notebook n'ayant le droit de connaître ni l'un
     * ni l'autre contexte.
     *
     * @return list<TaskHit>
     */
    public function taskResults(): array
    {
        return '' === trim($this->query) ? [] : $this->tasks->matching($this->query);
    }

    /**
     * Au repos, rien : l'écran déroule le carnet, pas tout le reste avec.
     *
     * @return list<RoutineHit>
     */
    public function routineResults(): array
    {
        return '' === trim($this->query) ? [] : $this->routines->matching($this->query);
    }
}
