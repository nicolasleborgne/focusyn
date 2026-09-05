<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Racine d'agrégat : frontière de cohérence et source des événements de domaine.
 *
 * Les événements sont accumulés pendant les opérations métier puis relâchés
 * par l'infrastructure au moment de l'enregistrement. Le domaine ne connaît donc
 * ni bus de messages ni transaction : il se contente de constater ce qui s'est
 * produit.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    /**
     * Vide la file et retourne les événements accumulés.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
