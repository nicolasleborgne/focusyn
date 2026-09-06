<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\RemoveMember;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveMemberHandler
{
    public function __construct(
        private OrganizationRepository $organizations,
        private CurrentTenant $tenant,
    ) {
    }

    public function __invoke(RemoveMember $command): void
    {
        $organization = $this->organizations->ofId(OrganizationId::fromString($this->tenant->id()->toString()))
            ?? throw new InvalidArgumentException('Organisation introuvable.');

        // Les notes écrites restent : elles appartiennent à l'organisation, pas
        // à la personne. Retirer quelqu'un ne détruit pas son travail.
        $organization->removeMember(MemberId::fromString($command->memberId));

        $this->organizations->save($organization);
    }
}
