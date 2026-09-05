<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La coquille est le cadre de tous les écrans : si elle casse, tout casse.
 */
final class ShellNavigationTest extends WebTestCase
{
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
        $crawler = $client->request('GET', $uri);

        $current = $crawler->filter('.fx-sidebar [aria-current="page"]');

        self::assertCount(1, $current, 'Une seule entrée de la barre latérale doit porter aria-current.');
        self::assertStringContainsString('fx-nav-item--current', (string) $current->attr('class'));
    }

    public function testTheSidebarShowsTheNotebookCounters(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        $counts = $crawler->filter('.fx-sidebar__nav .fx-nav-item__count')->each(
            static fn ($node): string => trim($node->text()),
        );

        self::assertSame(['8', '11'], $counts, 'Bibliothèque et Tâches affichent respectivement le nombre de notes et de tâches ouvertes.');
    }

    public function testTheHomeScreenListsWhatIsPending(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertCount(4, $crawler->filter('.fx-stat'));
        self::assertCount(3, $crawler->filter('.fx-note-row'));
        self::assertCount(5, $crawler->filter('.fx-task-line'));
    }

    public function testScreensNotYetBuiltAnnounceThemselvesWithoutFailing(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/bibliotheque');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'contexte borné',
            $crawler->filter('.fx-upcoming__text')->text(),
        );
    }
}
