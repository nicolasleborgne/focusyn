<?php

declare(strict_types=1);

namespace App\Tests\Functional\Assistant;

use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use App\Tests\Double\Assistant\FakeCompletion;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Brancher un fournisseur, puis interroger le modèle sur une note.
 *
 * La barrière la plus importante n'est pas la clé mais le consentement : sans
 * lui, rien ne part, et ces tests le vérifient avant tout le reste.
 */
final class AssistantJourneyTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;
    use ResetDatabase;

    public function testTheSectionStartsEmptyAndSaysWhatItDoesWithTheKey(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $section = $client->request('GET', '/reglages')->filter('.fx-assistant__statement');

        self::assertStringContainsString('chiffrée avant d\'être conservée', $section->text());
        self::assertStringContainsString(
            'non renseignée',
            $client->getCrawler()->filter('.fx-assistant__key-state')->text(),
        );
    }

    public function testAStoredKeyIsNeverShownBack(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);
        $crawler = $client->request('GET', '/reglages');

        self::assertStringContainsString('enregistrée, chiffrée', $crawler->filter('.fx-assistant__key-state')->text());
        self::assertStringNotContainsString('sk-ant-le-secret', (string) $client->getResponse()->getContent());
        self::assertSame('', (string) $crawler->filter('input[name="apiKey"]')->attr('value'));
    }

    public function testWhatIsStoredInTheDatabaseIsNotTheKey(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);

        $settings = self::getContainer()->get(AssistantSettingsRepository::class);
        self::assertInstanceOf(AssistantSettingsRepository::class, $settings);
        $stored = $settings->ofOwner(OwnerId::fromString($this->accountId($account->getUserIdentifier())));

        self::assertNotNull($stored?->key());
        self::assertStringNotContainsString('sk-ant', $stored->key()->cipher());
    }

    public function testChangingProviderDropsTheKey(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);

        $this->adjust($client, ['provider' => 'openai']);

        self::assertStringContainsString(
            'non renseignée',
            $client->request('GET', '/reglages')->filter('.fx-assistant__key-state')->text(),
        );
    }

    public function testAModelThatTheProviderDoesNotOfferIsRejected(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        // Le message est lu sur la page d'arrivée : un message éclair ne
        // survit pas à une seconde requête.
        $crawler = $this->adjust($client, ['model' => 'gpt-5']);

        self::assertStringContainsString('n\'a pas été accepté', $crawler->filter('.fx-auth__error')->text());
    }

    public function testWithoutConsentThePanelIsNotEvenRendered(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');

        self::assertCount(0, $client->request('GET', '/notes/'.$noteId)->filter('.fx-assistant__actions'));

        // Et le cas d'usage refuse aussi, sans l'écran pour le protéger.
        $panel = $this->createLiveComponent('AssistantPanel', ['noteId' => $noteId], $client)->actingAs($account);
        $rendered = $panel->call('run', ['action' => 'summarise'])->render()->toString();

        self::assertStringContainsString('tant que vous n&#039;y avez pas consenti', $rendered);
        self::assertNull($this->provider()->lastPrompt, 'Rien ne doit partir sans consentement.');
    }

    public function testOnceConsentedAQuickActionSendsTheWholeNote(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->consentToAssistant($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet', 'Trois nuits courtes de suite.');

        $panel = $this->createLiveComponent('AssistantPanel', ['noteId' => $noteId], $client)->actingAs($account);
        $rendered = $panel->call('run', ['action' => 'summarise'])->render()->toString();

        self::assertStringContainsString('une première puce', $rendered);

        $sent = (string) $this->provider()->lastPrompt;
        self::assertStringContainsString('Résume cette note en 3 puces', $sent);
        self::assertStringContainsString('# Le sommeil comme sujet', $sent);
        self::assertStringContainsString('Trois nuits courtes de suite.', $sent);
        self::assertSame('sk-ant-le-secret', $this->provider()->lastKey, 'La clé est descellée pour l\'appel.');
    }

    public function testSendingOnlyTheSelectionNeverLeaksTheRestOfTheNote(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->consentToAssistant($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);
        $this->adjust($client, ['wholeNote' => '0']);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet', 'Un passage confidentiel.');

        $this->createLiveComponent('AssistantPanel', ['noteId' => $noteId], $client)
            ->actingAs($account)
            ->set('selection', 'un extrait choisi')
            ->call('run', ['action' => 'clarify']);

        $sent = (string) $this->provider()->lastPrompt;
        self::assertStringContainsString('un extrait choisi', $sent);
        self::assertStringNotContainsString('confidentiel', $sent);
    }

    public function testAskingWithNothingSelectedIsRefusedRatherThanFallingBackOnTheNote(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->consentToAssistant($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);
        $this->adjust($client, ['wholeNote' => '0']);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet', 'Un passage confidentiel.');

        $rendered = $this->createLiveComponent('AssistantPanel', ['noteId' => $noteId], $client)
            ->actingAs($account)
            ->call('run', ['action' => 'clarify'])
            ->render()
            ->toString();

        self::assertStringContainsString('sélectionné, et le réglage', $rendered);
        self::assertNull($this->provider()->lastPrompt);
    }

    public function testAFreePromptIsSentAndTheFieldIsCleared(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->consentToAssistant($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet', 'Trois nuits courtes.');

        $panel = $this->createLiveComponent('AssistantPanel', ['noteId' => $noteId], $client)->actingAs($account);
        $rendered = $panel->set('prompt', 'Que manque-t-il ?')->call('send')->render()->toString();

        self::assertStringContainsString('Que manque-t-il ?', (string) $this->provider()->lastPrompt);
        self::assertStringNotContainsString('value="Que manque-t-il ?"', $rendered);
    }

    public function testARefusalIsShownWithoutTheProviderOwnWords(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->consentToAssistant($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');

        // Le client redémarre le noyau à chaque requête : sans cela, le double
        // que l'on règle ici ne serait pas celui qui répond ensuite.
        $client->disableReboot();
        $this->provider()->refusal = 'assistant.error.rejected_key';

        $rendered = $this->createLiveComponent('AssistantPanel', ['noteId' => $noteId], $client)
            ->actingAs($account)
            ->call('run', ['action' => 'summarise'])
            ->render()
            ->toString();

        self::assertStringContainsString('a refusé cette clé', $rendered);
    }

    public function testTestingTheConnectionReportsTheModelAndTheLatency(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->adjust($client, ['apiKey' => 'sk-ant-le-secret']);

        $crawler = $client->request('GET', '/reglages');
        $client->submit($crawler->filter('form[action="/reglages/assistant/test"]')->form());
        $crawler = $client->followRedirect();

        self::assertStringContainsString('claude-sonnet-4-5', $crawler->filter('.fx-assistant__test-status')->text());
        self::assertStringContainsString('ms', $crawler->filter('.fx-assistant__test-status')->text());
    }

    public function testTestingWithoutAnyKeySaysSo(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/reglages');
        $client->submit($crawler->filter('form[action="/reglages/assistant/test"]')->form());
        $crawler = $client->followRedirect();

        self::assertStringContainsString('Aucun fournisseur n\'est branché', $crawler->filter('.fx-assistant__test-status')->text());
    }

    public function testALocalProviderTakesAnAddressInsteadOfAKey(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, ['provider' => 'ollama']);
        $this->adjust($client, ['baseUrl' => 'http://localhost:11434']);

        $crawler = $client->request('GET', '/reglages');

        self::assertCount(1, $crawler->filter('input[name="baseUrl"]'));
        self::assertCount(0, $crawler->filter('input[name="apiKey"]'));
        self::assertSame('http://localhost:11434', $crawler->filter('input[name="baseUrl"]')->attr('value'));

        // L'écran ne fait pas deviner ce qui sera accepté.
        self::assertSame(
            ['http://localhost:11434'],
            $crawler->filter('#fx-assistant-addresses option')->extract(['value']),
        );
    }

    /**
     * L'adresse d'un fournisseur local n'est pas libre : celle que l'on saisit
     * est appelée **par le serveur**, et le serveur n'atteint pas la machine de
     * la personne — seulement son propre réseau. Sans cette liste, l'écran des
     * réglages prêtait le serveur comme relais vers la base de données, les
     * services voisins ou le point de métadonnées de l'hébergeur.
     */
    public function testAnAddressOutsideTheAllowedListIsRefused(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);

        $this->adjust($client, ['provider' => 'ollama']);
        $crawler = $this->adjust($client, ['baseUrl' => 'http://169.254.169.254']);

        self::assertStringContainsString('pas été accepté', $crawler->filter('.fx-auth__error')->text());

        $settings = self::getContainer()->get(AssistantSettingsRepository::class);
        self::assertInstanceOf(AssistantSettingsRepository::class, $settings);
        $stored = $settings->ofOwner(OwnerId::fromString($this->accountId($account->getUserIdentifier())));

        self::assertNull($stored?->baseUrl(), 'L\'adresse refusée ne doit pas avoir été enregistrée.');
    }

    /** @param array<string, string> $values */
    private function adjust(KernelBrowser $client, array $values): Crawler
    {
        $token = (string) $client->request('GET', '/reglages')
            ->filter('form[action="/reglages/assistant"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/reglages/assistant', [...$values, '_token' => $token]);

        return $client->followRedirect();
    }

    private function consentToAssistant(KernelBrowser $client): void
    {
        $token = (string) $client->request('GET', '/reglages')
            ->filter('form[action="/reglages/donnees"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/reglages/donnees', [
            'consent' => 'assistant',
            'granted' => '1',
            '_token' => $token,
        ]);
        $client->followRedirect();
    }

    private function provider(): FakeCompletion
    {
        // Déclaré sous `when@test` : le conteneur que lit l'analyse statique
        // est celui de dev, où il n'existe pas.
        /** @phpstan-ignore symfonyContainer.serviceNotFound */
        $provider = self::getContainer()->get(FakeCompletion::class);
        self::assertInstanceOf(FakeCompletion::class, $provider);

        return $provider;
    }

    private function accountId(string $email): string
    {
        $users = self::getContainer()->get(\App\Identity\Domain\Repository\UserRepository::class);
        self::assertInstanceOf(\App\Identity\Domain\Repository\UserRepository::class, $users);
        $user = $users->ofEmail(\App\Identity\Domain\Model\EmailAddress::fromString($email));
        self::assertNotNull($user);

        return $user->id()->toString();
    }
}
