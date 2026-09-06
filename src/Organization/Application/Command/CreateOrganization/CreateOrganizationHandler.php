<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\CreateOrganization;

use App\Organization\Application\Port\SlugGenerator;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\MembershipId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Billing\Entitlements;
use App\Shared\Application\Billing\PlanLimitReached;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Ouvre une organisation d'équipe, dont l'auteur est propriétaire.
 *
 * Distincte de l'espace personnel créé à l'inscription : celui-ci ne s'invite
 * pas et ne se supprime pas, c'est le carnet de son propriétaire.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateOrganizationHandler
{
    public function __construct(
        private OrganizationRepository $organizations,
        private SlugGenerator $slugs,
        private CurrentAccount $account,
        private Entitlements $entitlements,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateOrganization $command): OrganizationId
    {
        if (!$this->entitlements->allowsTeams()) {
            throw PlanLimitReached::teams();
        }

        $name = trim($command->name);

        if ('' === $name) {
            throw new InvalidArgumentException('Une organisation doit avoir un nom.');
        }

        $owner = MemberId::fromString(
            $this->account->idOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.'),
        );

        $organization = Organization::create(
            OrganizationId::generate(),
            $name,
            $this->uniqueSlugFor($name),
            $owner,
            MembershipId::generate(),
            $this->clock->now(),
        );

        $this->organizations->save($organization);

        return $organization->id();
    }

    /**
     * Deux équipes peuvent porter le même nom ; leurs adresses, non. On suffixe
     * plutôt que de refuser : le nom appartient à celui qui le choisit.
     */
    private function uniqueSlugFor(string $name): string
    {
        $base = $this->slugs->slugify($name);
        $slug = $base;

        for ($suffix = 2; $this->organizations->slugIsTaken($slug); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
