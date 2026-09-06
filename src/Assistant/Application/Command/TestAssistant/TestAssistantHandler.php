<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command\TestAssistant;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Exception\KeyVaultUnavailable;
use App\Assistant\Application\Port\KeyVault;
use App\Assistant\Application\Provider\ProviderRegistry;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use App\Shared\Application\Account\CurrentAccount;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class TestAssistantHandler
{
    public function __construct(
        private AssistantSettingsRepository $settings,
        private ProviderRegistry $providers,
        private KeyVault $vault,
        private CurrentAccount $account,
    ) {
    }

    /** @return array{model: string, milliseconds: int} */
    public function __invoke(TestAssistant $command): array
    {
        $accountId = $this->account->idOrNull() ?? throw new AssistantRefused('assistant.error.no_account');
        $settings = $this->settings->ofOwner(OwnerId::fromString($accountId));

        if (null === $settings || !$settings->isUsable()) {
            throw new AssistantRefused('assistant.error.not_configured');
        }

        $key = $settings->key();

        try {
            $plainKey = null === $key ? null : $this->vault->unseal($key);
        } catch (KeyVaultUnavailable) {
            throw new AssistantRefused('assistant.error.unsealable_key');
        }

        $startedAt = hrtime(true);

        $this->providers->for($settings->provider())->complete(
            $settings->model(),
            'Réponds par un seul mot.',
            'Dis « prêt ».',
            $plainKey,
            $settings->baseUrl(),
        );

        return [
            'model' => $settings->model(),
            'milliseconds' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }
}
