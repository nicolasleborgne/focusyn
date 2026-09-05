<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Functional\LogsIn;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * La coquille est le cadre de tous les écrans : si elle casse, tout casse.
 */
final class ShellNavigationTest extends WebTestCase
{
    use Factories;
    use LogsIn;

    /** @return iterable<string, array{string, string}> */
    public static function frenchScreens(): iterable
    {
        yield 'accueil' => ['/', 'home'];
        yield 'bibliothèque' => ['/bibliotheque', 'library'];
        yield 'recherche' => ['/recherche', 'search'];
        yield 'tâches' => ['/taches', 'tasks'];
        yield 'réglages' => ['/reglages', 'settings'];
    }

    #[DataProvider('frenchScreens')]
    public function testEveryScreenRendersTheShell(string $uri, string $section): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $crawler = $client->request('GET', $uri);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.fx-sidebar'), 'La barre latérale doit être rendue.');
        self::assertCount(1, $crawler->filter('.fx-tabbar'), 'La barre d\'onglets doit être rendue : elle est masquée par CSS, pas retirée du balisage.');
        self::assertGreaterThan(0, $crawler->filter('.fx-nav-item')->count());
    }

    #[DataProvider('frenchScreens')]
    public function testTheCurrentSectionIsMarkedForAssistiveTechnologies(string $uri, string $section): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $crawler = $client->request('GET', $uri);

        $current = $crawler->filter('.fx-sidebar [aria-current="page"]');

        self::assertCount(1, $current, 'Une seule entrée de la barre latérale doit porter aria-current.');
        self::assertStringContainsString('fx-nav-item--current', (string) $current->attr('class'));
    }

    public function testTheSidebarCountsTheNotesActuallyWritten(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/');
        self::assertSame('0', trim($crawler->filter('.fx-sidebar__nav .fx-nav-item__count')->first()->text()));

        $this->writeNote($client, 'Deux sommeils');
        $this->writeNote($client, 'Extraction du café');

        $crawler = $client->request('GET', '/');
        self::assertSame(
            '2',
            trim($crawler->filter('.fx-sidebar__nav .fx-nav-item__count')->first()->text()),
            'Le compteur de la barre latérale vient du carnet réel, plus d\'un jeu de données figé.',
        );
    }

    public function testTheHomeScreenShowsTheNotesJustWritten(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Deux sommeils');

        $crawler = $client->request('GET', '/');

        self::assertCount(4, $crawler->filter('.fx-stat'));
        self::assertCount(1, $crawler->filter('.fx-note-row'));
        self::assertStringContainsString('Deux sommeils', $crawler->filter('.fx-note-row')->text());
    }

    public function testEveryNavigationEntryLeadsToItsOwnScreen(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        // Plus aucun écran d'attente : les cinq destinations sont construites.
        foreach (['/' => 'Reprendre le fil', '/bibliotheque' => 'Bibliothèque', '/recherche' => 'Recherche', '/taches' => 'Tâches', '/reglages' => 'Réglages'] as $uri => $heading) {
            $crawler = $client->request('GET', $uri);

            self::assertResponseIsSuccessful(\sprintf('%s doit répondre.', $uri));
            self::assertStringContainsString(
                $heading,
                $crawler->filter('h1')->text(),
                \sprintf('%s doit afficher son propre titre.', $uri),
            );
        }
    }
}
