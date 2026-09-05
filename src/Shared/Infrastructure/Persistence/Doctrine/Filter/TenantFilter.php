<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Filter;

use App\Shared\Domain\TenantScoped;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;

/**
 * Cloisonnement par organisation, appliqué au niveau du SQL.
 *
 * Ajoute `organization_id = :tenant` à toute requête portant sur un agrégat
 * marqué `TenantScoped`. Le contrôle vit ici plutôt que dans chaque dépôt
 * parce qu'un oubli, à cet endroit, ne se voit pas : la requête fonctionne et
 * retourne simplement les données de quelqu'un d'autre.
 *
 * Les entités non marquées — comptes, organisations, appartenances — sont
 * ignorées : elles n'appartiennent à aucune organisation, ou les définissent.
 */
final class TenantFilter extends SQLFilter
{
    public const string PARAMETER = 'tenant_id';
    public const string COLUMN = 'organization_id';

    /** @param ClassMetadata<object> $targetEntity */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->getReflectionClass()->implementsInterface(TenantScoped::class)) {
            return '';
        }

        try {
            $tenant = $this->getParameter(self::PARAMETER);
        } catch (InvalidArgumentException) {
            // Filtre activé sans organisation : on ne laisse rien passer.
            // Échouer ouvert reviendrait à tout montrer à tout le monde.
            return \sprintf('%s.%s IS NULL', $targetTableAlias, self::COLUMN);
        }

        return \sprintf('%s.%s = %s', $targetTableAlias, self::COLUMN, $tenant);
    }
}
