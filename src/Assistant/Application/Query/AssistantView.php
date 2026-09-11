<?php

declare(strict_types=1);

namespace App\Assistant\Application\Query;

use App\Assistant\Domain\Model\Provider;

final readonly class AssistantView
{
    /** @param list<string> $localAddresses Adresses locales autorisées par l'exploitant. */
    public function __construct(
        public bool $consented,
        public Provider $provider,
        public string $model,
        public bool $hasKey,
        public ?string $baseUrl,
        public bool $wholeNote,
        public bool $usable,
        public array $localAddresses = [],
    ) {
    }

    /**
     * Les fournisseurs que l'écran a le droit de proposer.
     *
     * Sans adresse autorisée, le fournisseur local n'apparaît pas du tout :
     * un choix qui ne peut mener qu'à un refus est pire que pas de choix,
     * comme la ligne « notifications système » sans clés VAPID.
     *
     * @return list<Provider>
     */
    public function offeredProviders(): array
    {
        return array_values(array_filter(
            Provider::cases(),
            fn (Provider $provider): bool => !$provider->isLocal() || [] !== $this->localAddresses,
        ));
    }

    /** Le panneau ne se rend que si les deux conditions sont réunies. */
    public function isAvailable(): bool
    {
        return $this->consented && $this->usable;
    }
}
