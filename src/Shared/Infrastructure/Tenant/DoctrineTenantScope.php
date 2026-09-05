<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenant;

use App\Shared\Application\Tenant\TenantScope;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Persistence\Doctrine\Filter\TenantFilter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @template T
 *
 * @implements TenantScope<T>
 */
final readonly class DoctrineTenantScope implements TenantScope
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function runAs(TenantId $tenant, callable $work): mixed
    {
        $filters = $this->entityManager->getFilters();
        $wasEnabled = $filters->isEnabled('tenant');

        $filter = $wasEnabled ? $filters->getFilter('tenant') : $filters->enable('tenant');
        $previous = $wasEnabled && $filter->hasParameter(TenantFilter::PARAMETER)
            ? $filter->getParameter(TenantFilter::PARAMETER)
            : null;

        $filter->setParameter(TenantFilter::PARAMETER, $tenant->toString());

        try {
            return $work();
        } finally {
            // On restitue l'état exact : un traitement imbriqué ne doit pas
            // laisser la requête suivante travailler dans la mauvaise
            // organisation.
            if (!$wasEnabled) {
                $filters->disable('tenant');
            } elseif (null !== $previous) {
                $filter->setParameter(TenantFilter::PARAMETER, trim($previous, "'"));
            }
        }
    }
}
