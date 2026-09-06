<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notebook;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Double\Assistant\FakeCompletion;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * « Ce qui se dégage », proposé par l'assistant.
 *
 * Deux promesses à tenir : rien n'est lu sans consentement, et rien n'est
 * enregistré sans qu'on l'ait gardé.
 */
final class ObsessionSynthesisTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;
    use ResetDatabase;

    public function testWithoutConsentNothingIsAskedAndNothingIsRead(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->tag($client, $this->writeNote($client, 'Le sommeil biphasique', 'La nuit se coupait en deux.'), 'Sommeil');
        $client->disableReboot();

        $rendered = $this->synthesis($client, $account)->call('write')->render()->toString();

        self::assertStringContainsString('tant que vous n', $rendered);
        // Rien n'est parti : ni la note, ni même la demande.
        self::assertNull($this->provider()->lastPrompt);
    }

    public function testTheProposalIsShownButNotSavedUntilItIsKept(): void
    {
        $client = self::createClient();
        $account = $this->prepare($client);

        $component = $this->synthesis($client, $account);
        $rendered = $component->call('write')->render()->toString();

        self::assertStringContainsString('une première puce', $rendered);
        self::assertStringContainsString('enregistré tant que vous', $rendered);

        // L'écran de l'obsession, lui, ne montre encore rien : la proposition
        // vit dans le composant, pas en base.
        self::assertCount(0, $client->request('GET', '/obsessions/sommeil')->filter('.fx-points__item'));
    }

    public function testKeepingWritesThePointsForGood(): void
    {
        $client = self::createClient();
        $account = $this->prepare($client);

        $component = $this->synthesis($client, $account);
        $component->call('write');
        $component->call('keep');

        $crawler = $client->request('GET', '/obsessions/sommeil');

        self::assertCount(2, $crawler->filter('.fx-points__item'));
        self::assertStringContainsString('une première puce', $crawler->filter('.fx-points')->text());
    }

    public function testDiscardingLeavesNothingBehind(): void
    {
        $client = self::createClient();
        $account = $this->prepare($client);

        $component = $this->synthesis($client, $account);
        $component->call('write');
        $rendered = $component->call('discard')->render()->toString();

        self::assertStringNotContainsString('une première puce', $rendered);
        self::assertCount(0, $client->request('GET', '/obsessions/sommeil')->filter('.fx-points__item'));
    }

    public function testBulletsAndNumbersAreStrippedFromWhatTheModelReturns(): void
    {
        $client = self::createClient();
        $account = $this->prepare($client);
        $this->provider()->answer = "1. La veille nocturne est un fait\n- La lumière est la variable\n• Le reste est de l'anxiété";

        $component = $this->synthesis($client, $account);
        $component->call('write');
        $component->call('keep');

        $points = $client->request('GET', '/obsessions/sommeil')->filter('.fx-points__text');

        // La consigne les interdit, un modèle en remet toujours, et l'écran
        // numérote déjà : les rendre deux fois se verrait.
        self::assertSame('La veille nocturne est un fait', $points->first()->text());
        self::assertStringNotContainsString('•', $points->text());
    }

    public function testTheNotesOfTheObsessionAreWhatIsSent(): void
    {
        $client = self::createClient();
        $account = $this->prepare($client);

        $this->synthesis($client, $account)->call('write');

        $sent = (string) $this->provider()->lastPrompt;

        self::assertStringContainsString('Le sommeil biphasique', $sent);
        // L'invite est coupée en lignes : on s'ancre sur un fragment contigu.
        self::assertStringContainsString('cherche ce qui se répond', $sent);
    }

    /** Consentement, clé, et une note à se mettre sous la dent. */
    private function prepare(KernelBrowser $client): SecurityUser
    {
        $account = $this->logIn($client);
        $this->consentToAssistant($client);
        $this->giveKey($client);
        $this->tag($client, $this->writeNote($client, 'Le sommeil biphasique', 'La nuit se coupait en deux.'), 'Sommeil');
        $client->disableReboot();

        return $account;
    }

    private function tag(KernelBrowser $client, string $noteId, string $obsession): void
    {
        $token = (string) $client->request('GET', '/notes/'.$noteId)
            ->filter('form[action="/notes/'.$noteId.'/obsessions"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/notes/'.$noteId.'/obsessions', ['_token' => $token, 'obsessions' => $obsession]);
        $client->followRedirect();
    }

    private function synthesis(KernelBrowser $client, SecurityUser $account): TestLiveComponent
    {
        return $this->createLiveComponent('ObsessionSynthesis', ['slug' => 'sommeil'], $client)->actingAs($account);
    }

    private function giveKey(KernelBrowser $client): void
    {
        $token = (string) $client->request('GET', '/reglages')
            ->filter('form[action="/reglages/assistant"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/reglages/assistant', ['apiKey' => 'sk-ant-le-secret', '_token' => $token]);
        $client->followRedirect();
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
        /** @phpstan-ignore symfonyContainer.serviceNotFound */
        $provider = self::getContainer()->get(FakeCompletion::class);
        self::assertInstanceOf(FakeCompletion::class, $provider);

        return $provider;
    }
}
