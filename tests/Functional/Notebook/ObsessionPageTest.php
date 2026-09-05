<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notebook;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

/**
 * Une obsession n'a pas besoin d'être créée : elle naît de la première note qui
 * la mentionne. Sa fiche éditoriale, elle, est facultative.
 */
final class ObsessionPageTest extends WebTestCase
{
    use Factories;
    use LogsIn;

    public function testTaggingANoteMakesTheObsessionReachable(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeTaggedNote($client, 'Deux sommeils', 'Sommeil, Histoire');

        $crawler = $client->request('GET', '/obsessions/sommeil');

        self::assertResponseIsSuccessful();
        self::assertSame('Sommeil', trim($crawler->filter('h1')->text()));
        self::assertStringContainsString('Deux sommeils', $crawler->filter('.fx-note-row__title')->text());
    }

    public function testTheHeaderCountsTheNotesItGathers(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeTaggedNote($client, 'Deux sommeils', 'Sommeil');
        $this->writeTaggedNote($client, 'Carnet de douze semaines', 'Sommeil');

        $crawler = $client->request('GET', '/obsessions/sommeil');

        self::assertStringContainsString('Obsession · 2 notes', $crawler->filter('.fx-app__content--obsession .fx-eyebrow')->first()->text());
    }

    public function testAnObsessionWithoutRecordShowsNoDescription(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeTaggedNote($client, 'Deux sommeils', 'Sommeil');

        $crawler = $client->request('GET', '/obsessions/sommeil');

        self::assertCount(0, $crawler->filter('.fx-obsession-page__blurb'));
        self::assertCount(0, $crawler->filter('.fx-points__item'));
    }

    public function testWritingTheRecordShowsItOnThePage(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeTaggedNote($client, 'Deux sommeils', 'Sommeil');

        $this->describe($client, 'sommeil', 'Segmentation, lumière, anxiété nocturne.', [
            'La veille nocturne est un fait historique.',
            'La lumière est la variable, pas la volonté.',
        ]);

        $crawler = $client->request('GET', '/obsessions/sommeil');
        self::assertSame('Segmentation, lumière, anxiété nocturne.', $crawler->filter('.fx-obsession-page__blurb')->text());
        self::assertCount(2, $crawler->filter('.fx-points__item'));
        self::assertSame('01', $crawler->filter('.fx-points__number')->first()->text());
    }

    public function testEmptyingTheRecordLeavesTheObsessionIntact(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeTaggedNote($client, 'Deux sommeils', 'Sommeil');
        $this->describe($client, 'sommeil', 'Une accroche.', ['Un point.']);

        $this->describe($client, 'sommeil', '', []);

        $crawler = $client->request('GET', '/obsessions/sommeil');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.fx-obsession-page__blurb'));
        self::assertStringContainsString('Deux sommeils', $crawler->text());
    }

    public function testAnObsessionNobodyMentionsDoesNotExist(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $client->request('GET', '/obsessions/fermentation');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testTheRecordOfAnotherAccountIsInvisible(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'alice@focusyn.fr');
        $this->writeTaggedNote($client, 'Secret industriel', 'Café');
        $this->describe($client, 'cafe', 'Formule confidentielle.', []);

        $this->signOut($client);
        $this->logIn($client, 'bob@focusyn.fr');
        $client->request('GET', '/obsessions/cafe');

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $client->getResponse()->getStatusCode(),
            'Ni les notes ni la fiche d\'une autre organisation ne doivent transparaître.',
        );
    }

    public function testUntaggingRemovesTheNoteFromTheObsession(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeTaggedNote($client, 'Deux sommeils', 'Sommeil');

        $crawler = $client->request('GET', '/notes/'.$noteId);
        $client->submit($crawler->filter('.fx-note__obsessions')->form(['obsessions' => 'Histoire']));

        $client->request('GET', '/obsessions/sommeil');
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        $client->request('GET', '/obsessions/histoire');
        self::assertResponseIsSuccessful();
    }

    private function writeTaggedNote(KernelBrowser $client, string $title, string $obsessions): string
    {
        $noteId = $this->writeNote($client, $title);

        $crawler = $client->request('GET', '/notes/'.$noteId);
        $client->submit($crawler->filter('.fx-note__obsessions')->form(['obsessions' => $obsessions]));
        $client->followRedirect();

        return $noteId;
    }

    /** @param list<string> $points */
    private function describe(KernelBrowser $client, string $slug, string $blurb, array $points): void
    {
        $crawler = $client->request('GET', '/obsessions/'.$slug);
        $form = $crawler->filter('.fx-obsession-form')->form();
        $form['blurb'] = $blurb;
        $form['points'] = array_pad($points, 6, '');
        $client->submit($form);
        $client->followRedirect();
    }
}
