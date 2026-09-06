<?php

declare(strict_types=1);

namespace App\Assistant\Application\Port;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Domain\Model\Provider;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un appel à un modèle de langage.
 *
 * Une implémentation par fournisseur, choisie par `supports()`. Les quatre
 * parlent des dialectes différents : Anthropic sépare la consigne système du
 * message, OpenAI en fait un message de plus, Google renomme tout, Ollama imite
 * OpenAI. Le reste de l'application n'a pas à le savoir.
 */
#[AutoconfigureTag('app.chat_completion')]
interface ChatCompletion
{
    public function supports(Provider $provider): bool;

    /** @throws AssistantRefused */
    public function complete(
        string $model,
        string $systemPrompt,
        string $userPrompt,
        ?string $apiKey,
        ?string $baseUrl,
    ): string;
}
