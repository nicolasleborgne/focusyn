<?php

declare(strict_types=1);

namespace App\Tests\Functional\Task;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * Les listes à cocher, y compris les actions du Live Component : cocher, ajouter
 * et retirer passent réellement par le serveur.
 */
final class TaskJourneyTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;

    public function testAnEmptyBoardSaysSo(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/taches');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucune liste', $crawler->text());
    }

    public function testOpeningAListLandsOnIt(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/taches');
        $client->submit($crawler->filter('form[action="/taches/nouvelle"]')->form());
        $crawler = $client->followRedirect();

        self::assertRouteSame('task_list_show');
        self::assertSame('Nouvelle liste', trim($crawler->filter('.fx-note__title')->text()));
        self::assertStringContainsString('Cette liste est vide', $crawler->text());
    }

    public function testAddingATaskThroughTheComponentPersistsIt(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $component = $this->createLiveComponent('TaskChecklist', ['listId' => $listId], $client)
            ->actingAs($account);

        $component->set('draft', 'Écrire la synthèse Sommeil')->call('add');

        self::assertStringContainsString('Écrire la synthèse Sommeil', $component->render()->toString());

        // Et la page complète la montre aussi, donc c'est bien en base.
        $crawler = $client->request('GET', '/taches/'.$listId);
        self::assertStringContainsString('Écrire la synthèse Sommeil', $crawler->text());
    }

    public function testCheckingATaskMovesItToTheCompletedSection(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $component = $this->createLiveComponent('TaskChecklist', ['listId' => $listId], $client)->actingAs($account);
        $component->set('draft', 'Sortir le vélo')->call('add');

        $taskId = $this->firstTaskId($component->render()->toString());
        $rendered = $component->call('toggle', ['taskId' => $taskId])->render()->toString();

        self::assertStringContainsString('1 tâche faite', $rendered);
        self::assertStringContainsString('100 %', $rendered);
    }

    public function testCheckingTwiceBringsTheTaskBack(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $component = $this->createLiveComponent('TaskChecklist', ['listId' => $listId], $client)->actingAs($account);
        $component->set('draft', 'Sortir le vélo')->call('add');
        $taskId = $this->firstTaskId($component->render()->toString());

        $component->call('toggle', ['taskId' => $taskId]);
        $rendered = $component->call('toggle', ['taskId' => $taskId])->render()->toString();

        self::assertStringContainsString('1 tâche ouverte', $rendered);
        self::assertStringContainsString('0 %', $rendered);
    }

    public function testRemovingATaskTakesItOff(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $component = $this->createLiveComponent('TaskChecklist', ['listId' => $listId], $client)->actingAs($account);
        $component->set('draft', 'À retirer')->call('add');
        $taskId = $this->firstTaskId($component->render()->toString());

        $rendered = $component->call('remove', ['taskId' => $taskId])->render()->toString();

        self::assertStringNotContainsString('À retirer', $rendered);
    }

    public function testAnEmptyDraftAddsNothing(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $component = $this->createLiveComponent('TaskChecklist', ['listId' => $listId], $client)->actingAs($account);
        $rendered = $component->set('draft', '   ')->call('add')->render()->toString();

        self::assertStringContainsString('Cette liste est vide', $rendered);
    }

    public function testTheSidebarCountsOpenTasks(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $component = $this->createLiveComponent('TaskChecklist', ['listId' => $listId], $client)->actingAs($account);
        $component->set('draft', 'Une')->call('add');
        $component->set('draft', 'Deux')->call('add');

        $crawler = $client->request('GET', '/');
        $counts = $crawler->filter('.fx-sidebar__nav .fx-nav-item__count')->each(
            static fn ($node): string => trim($node->text()),
        );

        self::assertSame(['0', '2'], $counts, 'Bibliothèque puis Tâches : zéro note, deux tâches ouvertes.');
    }

    public function testDeletingAListRemovesItFromTheBoard(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $listId = $this->openList($client, 'Cette semaine');

        $crawler = $client->request('GET', '/taches/'.$listId);
        $client->submit($crawler->filter('.fx-note__danger')->form());
        $crawler = $client->followRedirect();

        self::assertStringContainsString('Liste supprimée', $crawler->text());
        self::assertStringContainsString('Aucune liste', $crawler->text());
    }

    public function testAListOfAnotherAccountIsNotFound(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'alice@focusyn.fr');
        $foreignId = $this->openList($client, 'Secret');

        $this->signOut($client);
        $this->logIn($client, 'bob@focusyn.fr');
        $client->request('GET', '/taches/'.$foreignId);

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function openList(KernelBrowser $client, string $name): string
    {
        $crawler = $client->request('GET', '/taches');
        $client->submit($crawler->filter('form[action="/taches/nouvelle"]')->form());
        $client->followRedirect();

        $listId = (string) $client->getRequest()->attributes->get('id');

        $client->submit($client->getCrawler()->filter('.fx-note__title-form')->form(['name' => $name]));
        $client->followRedirect();

        return $listId;
    }

    private function firstTaskId(string $html): string
    {
        self::assertSame(1, preg_match('/data-live-task-id-param="([0-9a-f-]{36})"/', $html, $matches));

        return $matches[1];
    }
}
