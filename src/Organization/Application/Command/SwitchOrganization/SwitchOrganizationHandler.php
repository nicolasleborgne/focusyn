<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\SwitchOrganization;

use App\Organization\Application\Port\PreferredOrganization;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Account\CurrentAccount;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Change l'organisation dans laquelle on travaille.
 *
 * Le choix est mémorisé en session, mais l'appartenance est vérifiée ici *et*
 * revérifiée à chaque requête : une session ne doit jamais être crue sur parole.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class SwitchOrganizationHandler
{
    public function __construct(
        private OrganizationRepository $organizations,
        private CurrentAccount $account,
        private PreferredOrganization $preference,
    ) {
    }

    public function __invoke(SwitchOrganization $command): void
    {
        $accountId = $this->account->idOrNull();

        if (null === $accountId) {
            return;
        }

        try {
            $member = MemberId::fromString($accountId);
        } catch (InvalidArgumentException) {
            return;
        }

        foreach ($this->organizations->ofMember($member) as $organization) {
            if ($organization instanceof Organization && $organization->id()->toString() === $command->organizationId) {
                $this->preference->remember($command->organizationId);

                return;
            }
        }
    }
}
