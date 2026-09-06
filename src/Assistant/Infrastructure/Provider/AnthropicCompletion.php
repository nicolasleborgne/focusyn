<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Provider;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Port\ChatCompletion;
use App\Assistant\Domain\Model\Provider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AnthropicCompletion implements ChatCompletion
{
    use ReadsCompletionResponses;

    public function __construct(
        private HttpClientInterface $http,
    ) {
    }

    public function supports(Provider $provider): bool
    {
        return Provider::Anthropic === $provider;
    }

    public function complete(string $model, string $systemPrompt, string $userPrompt, ?string $apiKey, ?string $baseUrl): string
    {
        if (null === $apiKey) {
            throw new AssistantRefused('assistant.error.no_key');
        }

        $payload = $this->send($this->http, 'POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => $model,
                'max_tokens' => self::MAX_TOKENS,
                // La consigne système est un champ à part chez Anthropic.
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ],
        ]);

        $text = '';

        foreach ($payload['content'] ?? [] as $block) {
            if (\is_array($block) && 'text' === ($block['type'] ?? null) && \is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        return $this->orRefuse($text);
    }
}
