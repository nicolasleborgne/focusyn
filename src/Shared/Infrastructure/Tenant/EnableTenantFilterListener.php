<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use App\Shared\Application\Tenant\CurrentTenant;
use App\Shared\Infrastructure\Persistence\Doctrine\Filter\TenantFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Arme le cloisonnement pour la requête en cours.
 *
 * Priorité négative : le pare-feu doit avoir établi qui est connecté avant que
 * l'on puisse savoir dans quelle organisation il travaille.
 *
 * Le filtre est activé même sans organisation courante — il ne laisse alors
 * rien passer. Activer seulement « quand on sait » transformerait un oubli
 * d'authentification en fuite de données.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -16)]
final readonly class EnableTenantFilterListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentTenant $tenant,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->entityManager->getFilters();
        $filter = $filters->isEnabled('tenant') ? $filters->getFilter('tenant') : $filters->enable('tenant');

        $current = $this->tenant->idOrNull();

        if (null !== $current) {
            $filter->setParameter(TenantFilter::PARAMETER, $current->toString());
        }
    }
}
