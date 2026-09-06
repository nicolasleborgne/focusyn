<?php

declare(strict_types=1);

namespace App\Organization\UI\Security;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Tenant\CurrentTenant;
use App\Shared\Domain\TenantId;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Les habilitations dans l'organisation courante.
 *
 * Un voteur plutôt qu'un rôle global : ce qu'on a le droit de faire dépend de
 * l'organisation dans laquelle on travaille, et la même personne peut y être
 * propriétaire ici et simple membre ailleurs.
 *
 * @extends Voter<string, mixed>
 */
final class OrganizationVoter extends Voter
{
    public const string MANAGE_MEMBERS = 'ORGANIZATION_MANAGE_MEMBERS';
    public const string ADMINISTER = 'ORGANIZATION_ADMINISTER';

    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly CurrentTenant $tenant,
        private readonly CurrentAccount $account,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MANAGE_MEMBERS, self::ADMINISTER], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $organization = $this->currentOrganization();
        $accountId = $this->account->idOrNull();

        if (null === $organization || null === $accountId) {
            return false;
        }

        try {
            $role = $organization->roleOf(MemberId::fromString($accountId));
        } catch (InvalidArgumentException) {
            return false;
        }

        if (null === $role) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE_MEMBERS => $role->canManageMembers(),
            self::ADMINISTER => $role->canAdministerOrganization(),
            default => false,
        };
    }

    private function currentOrganization(): ?Organization
    {
        $tenant = $this->tenant->idOrNull();

        return $tenant instanceof TenantId
            ? $this->organizations->ofId(OrganizationId::fromString($tenant->toString()))
            : null;
    }
}
