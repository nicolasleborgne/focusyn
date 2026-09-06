<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Tenant;

use App\Organization\Application\Port\PreferredOrganization;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Tenant\CurrentTenant;
use App\Shared\Application\Tenant\NoCurrentTenant;
use App\Shared\Domain\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Détermine l'organisation courante à partir des appartenances du compte
 * connecté.
 *
 * L'organisation choisie est mémorisée en session, mais jamais crue sur
 * parole : à chaque requête, on revérifie que le compte en est bien membre.
 * Sans cette revérification, une session survivant à un retrait d'équipe
 * continuerait de donner accès.
 */
final class MembershipCurrentTenant implements CurrentTenant, PreferredOrganization
{
    public const string SESSION_KEY = 'organization.current';

    private ?TenantId $resolved = null;
    private bool $attempted = false;

    public function __construct(
        private readonly CurrentAccount $account,
        private readonly OrganizationRepository $organizations,
        private readonly RequestStack $requests,
    ) {
    }

    public function idOrNull(): ?TenantId
    {
        if ($this->attempted) {
            return $this->resolved;
        }

        $this->attempted = true;

        return $this->resolved = $this->resolve();
    }

    public function id(): TenantId
    {
        return $this->idOrNull() ?? throw NoCurrentTenant::create();
    }

    /**
     * Le choix est mémorisé, jamais cru sur parole : `resolve()` revérifie
     * l'appartenance à chaque requête.
     */
    public function remember(string $organizationId): void
    {
        $this->requests->getSession()->set(self::SESSION_KEY, $organizationId);

        // La requête en cours a peut-être déjà résolu l'ancienne organisation.
        $this->attempted = false;
        $this->resolved = null;
    }

    private function resolve(): ?TenantId
    {
        $accountId = $this->account->idOrNull();

        if (null === $accountId) {
            return null;
        }

        $memberships = $this->organizations->ofMember(MemberId::fromString($accountId));

        if ([] === $memberships) {
            return null;
        }

        $preferred = $this->preferredFromSession();

        foreach ($memberships as $organization) {
            if ($preferred === $organization->id()->toString()) {
                return TenantId::fromString($preferred);
            }
        }

        // Par défaut, la plus ancienne : l'espace personnel ouvert à
        // l'inscription.
        return TenantId::fromString($memberships[0]->id()->toString());
    }

    private function preferredFromSession(): ?string
    {
        $request = $this->requests->getCurrentRequest();

        if (null === $request || !$request->hasSession(true)) {
            return null;
        }

        $stored = $request->getSession()->get(self::SESSION_KEY);

        return \is_string($stored) ? $stored : null;
    }
}
