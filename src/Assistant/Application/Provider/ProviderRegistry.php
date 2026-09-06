<?php

declare(strict_types=1);

namespace App\Assistant\Application\Provider;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Port\ChatCompletion;
use App\Assistant\Domain\Model\Provider;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Choisit l'adaptateur qui sait parler au fournisseur demandé.
 *
 * Un registre plutôt qu'un `match` : ajouter un fournisseur ne doit obliger à
 * toucher que le contexte Assistant, et une seule classe s'y ajoute.
 */
final readonly class ProviderRegistry
{
    /** @param iterable<ChatCompletion> $adapters */
    public function __construct(
        #[AutowireIterator('app.chat_completion')]
        private iterable $adapters,
    ) {
    }

    public function for(Provider $provider): ChatCompletion
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($provider)) {
                return $adapter;
            }
        }

        throw new AssistantRefused('assistant.error.unknown_provider');
    }
}
