<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\RenameOrganization;

use App\Organization\Application\Port\SlugGenerator;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RenameOrganizationHandler
{
    public function __construct(
        private OrganizationRepository $organizations,
        private SlugGenerator $slugs,
        private CurrentTenant $tenant,
    ) {
    }

    public function __invoke(RenameOrganization $command): void
    {
        $organization = $this->organizations->ofId(OrganizationId::fromString($this->tenant->id()->toString()))
            ?? throw new InvalidArgumentException('Organisation introuvable.');

        $name = trim($command->name);

        if ('' === $name) {
            throw new InvalidArgumentException('Une organisation doit avoir un nom.');
        }

        $slug = $this->slugs->slugify($name);

        // On ne renomme l'adresse que si elle est libre : deux organisations ne
        // peuvent pas la partager, et le nom seul n'a pas à être unique.
        $organization->rename($name, $this->organizations->slugIsTaken($slug) ? $organization->slug() : $slug);

        $this->organizations->save($organization);
    }
}
