<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure;

use App\Assistant\Domain\Model\Provider;
use App\Assistant\Infrastructure\Provider\AnthropicCompletion;
use App\Assistant\Infrastructure\Provider\GoogleCompletion;
use App\Assistant\Infrastructure\Provider\OpenAiCompletion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Les quatre fournisseurs parlent des dialectes différents.
 *
 * Rien ne sort de la machine ici : le client HTTP est simulé. Ce qui est
 * vérifié, c'est ce qu'on leur envoie — la clé au bon endroit, la consigne
 * système au bon champ — et ce qu'on sait lire de leur réponse.
 */
#[CoversClass(AnthropicCompletion::class)]
#[CoversClass(OpenAiCompletion::class)]
#[CoversClass(GoogleCompletion::class)]
final class ProviderAdaptersTest extends TestCase
{
    public function testAnthropicPutsTheSystemPromptInItsOwnField(): void
    {
        $seen = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'options' => $options];

            return new MockResponse((string) json_encode([
                'content' => [['type' => 'text', 'text' => '- une puce']],
            ]));
        });

        $out = (new AnthropicCompletion($client))->complete('claude-sonnet-4-5', 'Sois sobre.', 'Résume.', 'sk-ant-secret', null);

        self::assertSame('- une puce', $out);
        self::assertIsArray($seen);
        self::assertStringContainsString('api.anthropic.com', $seen['url']);

        $body = json_decode($seen['options']['body'], true);
        self::assertSame('Sois sobre.', $body['system']);
        self::assertSame('Résume.', $body['messages'][0]['content']);
        self::assertContains('x-api-key: sk-ant-secret', $seen['options']['headers']);
    }

    public function testOpenAiMakesTheSystemPromptAMessage(): void
    {
        $seen = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'options' => $options];

            return new MockResponse((string) json_encode([
                'choices' => [['message' => ['content' => 'texte']]],
            ]));
        });

        self::assertSame('texte', (new OpenAiCompletion($client))->complete('gpt-5', 'Sois sobre.', 'Résume.', 'sk-secret', null));
        self::assertIsArray($seen);

        $body = json_decode($seen['options']['body'], true);
        self::assertSame('system', $body['messages'][0]['role']);
        self::assertSame('Sois sobre.', $body['messages'][0]['content']);
    }

    public function testALocalProviderIsCalledAtItsOwnAddressAndWithoutAKey(): void
    {
        $seen = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'options' => $options];

            return new MockResponse((string) json_encode(['choices' => [['message' => ['content' => 'texte']]]]));
        });

        (new OpenAiCompletion($client))->complete('llama3.1', 'Sois sobre.', 'Résume.', null, 'http://localhost:11434');

        self::assertIsArray($seen);
        self::assertSame('http://localhost:11434/v1/chat/completions', $seen['url']);
        self::assertNotContains('Authorization: Bearer ', $seen['options']['headers']);
    }

    public function testGooglePassesItsKeyInAHeaderRatherThanTheAddress(): void
    {
        $seen = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'options' => $options];

            return new MockResponse((string) json_encode([
                'candidates' => [['content' => ['parts' => [['text' => 'texte']]]]],
            ]));
        });

        self::assertSame('texte', (new GoogleCompletion($client))->complete('gemini-2.5-pro', 'Sois sobre.', 'Résume.', 'AIza-secret', null));
        self::assertIsArray($seen);

        // Une adresse se retrouve dans les journaux du serveur et du proxy.
        self::assertStringNotContainsString('AIza-secret', $seen['url']);
        self::assertContains('x-goog-api-key: AIza-secret', $seen['options']['headers']);
    }

    public function testARejectedKeySaysSoRatherThanRelayingTheProviderMessage(): void
    {
        $client = new MockHttpClient(new MockResponse('{"error":{"message":"Invalid API key sk-ant-secret"}}', ['http_code' => 401]));

        $this->expectExceptionMessage('assistant.error.rejected_key');
        (new AnthropicCompletion($client))->complete('claude-sonnet-4-5', 'x', 'y', 'sk-ant-secret', null);
    }

    public function testAThrottledProviderIsDistinguishedFromARefusal(): void
    {
        $client = new MockHttpClient(new MockResponse('{}', ['http_code' => 429]));

        $this->expectExceptionMessage('assistant.error.rate_limited');
        (new OpenAiCompletion($client))->complete('gpt-5', 'x', 'y', 'sk-secret', null);
    }

    public function testAnUnreachableProviderIsNotAnUnreadableOne(): void
    {
        $client = new MockHttpClient(new MockResponse('pas du json'));

        $this->expectExceptionMessage('assistant.error.unreadable');
        (new OpenAiCompletion($client))->complete('gpt-5', 'x', 'y', 'sk-secret', null);
    }

    public function testAnEmptyAnswerIsRefusedRatherThanInserted(): void
    {
        $client = new MockHttpClient(new MockResponse((string) json_encode(['choices' => [['message' => ['content' => '   ']]]])));

        $this->expectExceptionMessage('assistant.error.empty');
        (new OpenAiCompletion($client))->complete('gpt-5', 'x', 'y', 'sk-secret', null);
    }

    public function testWithoutAKeyNothingIsEvenAttempted(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('Aucun appel ne doit partir sans clé.');
        });

        $this->expectExceptionMessage('assistant.error.no_key');
        (new AnthropicCompletion($client))->complete('claude-sonnet-4-5', 'x', 'y', null, null);
    }

    public function testEachAdapterAnswersForItsOwnProviders(): void
    {
        $client = new MockHttpClient();

        self::assertTrue((new AnthropicCompletion($client))->supports(Provider::Anthropic));
        self::assertFalse((new AnthropicCompletion($client))->supports(Provider::OpenAI));

        // Ollama expose volontairement le dialecte d'OpenAI.
        self::assertTrue((new OpenAiCompletion($client))->supports(Provider::Ollama));

        self::assertTrue((new GoogleCompletion($client))->supports(Provider::Google));
        self::assertFalse((new GoogleCompletion($client))->supports(Provider::Ollama));
    }
}
