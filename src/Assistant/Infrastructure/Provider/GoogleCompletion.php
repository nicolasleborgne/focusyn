<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Provider;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Port\ChatCompletion;
use App\Assistant\Domain\Model\Provider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleCompletion implements ChatCompletion
{
    use ReadsCompletionResponses;

    public function __construct(
        private HttpClientInterface $http,
    ) {
    }

    public function supports(Provider $provider): bool
    {
        return Provider::Google === $provider;
    }

    public function complete(string $model, string $systemPrompt, string $userPrompt, ?string $apiKey, ?string $baseUrl): string
    {
        if (null === $apiKey) {
            throw new AssistantRefused('assistant.error.no_key');
        }

        $payload = $this->send(
            $this->http,
            'POST',
            \sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', $model),
            [
                // La clé passe par un en-tête et non par la requête : une
                // adresse est journalisée, un en-tête beaucoup moins.
                'headers' => ['x-goog-api-key' => $apiKey],
                'json' => [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                    'generationConfig' => ['maxOutputTokens' => self::MAX_TOKENS],
                ],
            ],
        );

        $text = '';

        foreach ($payload['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (\is_array($part) && \is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $this->orRefuse($text);
    }
}
