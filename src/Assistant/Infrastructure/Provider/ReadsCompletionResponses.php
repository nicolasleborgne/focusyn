<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Provider;

use App\Assistant\Application\Exception\AssistantRefused;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ce que les quatre fournisseurs ont en commun : un appel, un code de retour,
 * un corps JSON.
 *
 * Les messages d'erreur sont des clés de traduction et jamais le corps renvoyé
 * par le fournisseur : celui-ci peut contenir n'importe quoi, y compris un
 * fragment de la requête — donc de la note.
 */
trait ReadsCompletionResponses
{
    private const int MAX_TOKENS = 700;
    private const int TIMEOUT = 45;

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function send(HttpClientInterface $http, string $method, string $url, array $options): array
    {
        try {
            $response = $http->request($method, $url, [...$options, 'timeout' => self::TIMEOUT]);
            $status = $response->getStatusCode();
            $body = $response->getContent(throw: false);
        } catch (TransportExceptionInterface) {
            throw new AssistantRefused('assistant.error.unreachable');
        }

        if (401 === $status || 403 === $status) {
            throw new AssistantRefused('assistant.error.rejected_key');
        }

        if (429 === $status) {
            throw new AssistantRefused('assistant.error.rate_limited');
        }

        if ($status >= 400) {
            throw new AssistantRefused('assistant.error.refused');
        }

        $payload = json_decode($body, true);

        if (!\is_array($payload)) {
            throw new AssistantRefused('assistant.error.unreadable');
        }

        return $payload;
    }

    private function orRefuse(string $text): string
    {
        $trimmed = trim($text);

        if ('' === $trimmed) {
            throw new AssistantRefused('assistant.error.empty');
        }

        return $trimmed;
    }
}
