<?php

declare(strict_types=1);

namespace App\Assistant\Application\Query;

use App\Assistant\Domain\Model\AssistantSettings;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Model\Provider;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Privacy\ConsentGate;

final readonly class AssistantQuery
{
    public function __construct(
        private AssistantSettingsRepository $settings,
        private ConsentGate $consent,
        private CurrentAccount $account,
    ) {
    }

    public function forCurrentAccount(): AssistantView
    {
        $accountId = $this->account->idOrNull();

        if (null === $accountId) {
            return self::empty();
        }

        $settings = $this->settings->ofOwner(OwnerId::fromString($accountId));

        return new AssistantView(
            consented: $this->consent->allows('assistant', $accountId),
            provider: $settings?->provider() ?? Provider::Anthropic,
            model: $settings?->model() ?? Provider::Anthropic->defaultModel(),
            hasKey: null !== $settings?->key(),
            baseUrl: $settings?->baseUrl(),
            wholeNote: $settings?->sendsWholeNote() ?? true,
            usable: $settings instanceof AssistantSettings && $settings->isUsable(),
        );
    }

    private static function empty(): AssistantView
    {
        return new AssistantView(
            consented: false,
            provider: Provider::Anthropic,
            model: Provider::Anthropic->defaultModel(),
            hasKey: false,
            baseUrl: null,
            wholeNote: true,
            usable: false,
        );
    }
}
