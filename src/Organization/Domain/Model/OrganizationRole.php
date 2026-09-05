<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function canManageMembers(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Member => false,
        };
    }

    /**
     * Facturation, changement de propriétaire, suppression de l'organisation :
     * réservés au propriétaire, un administrateur ne doit pas pouvoir supprimer
     * l'espace qui l'héberge.
     */
    public function canAdministerOrganization(): bool
    {
        return self::Owner === $this;
    }
}
