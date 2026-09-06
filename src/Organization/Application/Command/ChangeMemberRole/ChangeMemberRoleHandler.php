<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\ChangeMemberRole;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizationRole;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeMemberRoleHandler
{
    public function __construct(
        private OrganizationRepository $organizations,
        private CurrentTenant $tenant,
    ) {
    }

    public function __invoke(ChangeMemberRole $command): void
    {
        $organization = $this->organizations->ofId(OrganizationId::fromString($this->tenant->id()->toString()))
            ?? throw new InvalidArgumentException('Organisation introuvable.');

        // `changeRole` refuse de retirer le dernier propriétaire : la règle est
        // tenue par l'agrégat, pas ici.
        $organization->changeRole(MemberId::fromString($command->memberId), OrganizationRole::from($command->role));

        $this->organizations->save($organization);
    }
}
