<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Provider;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Port\ChatCompletion;
use App\Assistant\Domain\Model\Provider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI, et tout ce qui en parle le dialecte — dont Ollama, qui l'expose
 * volontairement.
 */
final readonly class OpenAiCompletion implements ChatCompletion
{
    use ReadsCompletionResponses;

    public function __construct(
        private HttpClientInterface $http,
    ) {
    }

    public function supports(Provider $provider): bool
    {
        return Provider::OpenAI === $provider || Provider::Ollama === $provider;
    }

    public function complete(string $model, string $systemPrompt, string $userPrompt, ?string $apiKey, ?string $baseUrl): string
    {
        // Un fournisseur local n'a pas de clé mais a une adresse ; l'hébergé,
        // l'inverse.
        if (null === $apiKey && null === $baseUrl) {
            throw new AssistantRefused('assistant.error.no_key');
        }

        $payload = $this->send(
            $this->http,
            'POST',
            ($baseUrl ?? 'https://api.openai.com').'/v1/chat/completions',
            [
                'headers' => null === $apiKey ? [] : ['Authorization' => 'Bearer '.$apiKey],
                'json' => [
                    'model' => $model,
                    'max_completion_tokens' => self::MAX_TOKENS,
                    // Ici la consigne système est un message de plus.
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
            ],
        );

        $content = $payload['choices'][0]['message']['content'] ?? null;

        return $this->orRefuse(\is_string($content) ? $content : '');
    }
}
