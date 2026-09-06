<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notebook;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le carnet tel qu'on s'en sert : écrire, renommer, sauvegarder, chercher,
 * supprimer — et ne jamais voir la note de quelqu'un d'autre.
 */
final class NotebookJourneyTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testAnEmptyLibraryInvitesToWrite(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/bibliotheque');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucune note', $crawler->filter('.fx-library__empty')->text());
    }

    public function testWritingANoteOpensTheEditorOnIt(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/bibliotheque');
        $client->submit($crawler->filter('form[action="/notes/nouvelle"]')->form());
        $crawler = $client->followRedirect();

        self::assertRouteSame('note_show');
        self::assertCount(1, $crawler->filter('.fx-note__editor'));
        // Le titre est une zone de texte extensible, pas un `input` : sa valeur
        // est son contenu, et un titre long reste lisible au lieu d'être tronqué.
        self::assertSame('Sans titre', trim($crawler->filter('.fx-note__title')->text()));
    }

    public function testTheEditorReceivesEverythingItNeedsToSaveOnItsOwn(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Deux sommeils');

        $editor = $client->getCrawler()->filter('.fx-note__editor');

        self::assertNotSame('', (string) $editor->attr('data-note-editor-save-url-value'));
        self::assertNotSame('', (string) $editor->attr('data-note-editor-token-value'));
        self::assertSame('note-editor', $editor->attr('data-controller'));
    }

    public function testTheBodySavedByTheEditorComesBackOnReload(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Deux sommeils', "# Deux sommeils\n\nLa nuit se coupait en deux.");

        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/notes/'.$noteId);

        self::assertStringContainsString(
            'La nuit se coupait en deux.',
            (string) $crawler->filter('.fx-note__editor')->attr('data-note-editor-body-value'),
        );
    }

    public function testRenamingIsReflectedInTheLibrary(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Deux sommeils');

        $crawler = $client->request('GET', '/bibliotheque');

        self::assertStringContainsString('Deux sommeils', $crawler->filter('.fx-note-row__title')->text());
    }

    public function testSearchFindsANoteByItsBody(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Deux sommeils', 'Avant l\'éclairage artificiel, la nuit se coupait en deux.');

        $crawler = $client->request('GET', '/recherche?query=éclairage');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Deux sommeils', $crawler->filter('.fx-note-row__title')->text());
    }

    public function testSearchShowsNothingUntilSomethingIsTyped(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Deux sommeils');

        $crawler = $client->request('GET', '/recherche');

        self::assertCount(0, $crawler->filter('.fx-note-row'));
        self::assertStringContainsString('Tapez pour chercher', $crawler->filter('.fx-search__empty')->text());
    }

    public function testDeletingRemovesTheNoteFromTheLibrary(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Deux sommeils');

        $crawler = $client->request('GET', '/notes/'.$noteId);
        $client->submit($crawler->filter('.fx-note__danger')->form());
        $crawler = $client->followRedirect();

        self::assertStringContainsString('Note supprimée', $crawler->text());
        self::assertCount(0, $crawler->filter('.fx-note-row'));
    }

    public function testThePreviewShowsTheNoteWithoutItsMarks(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Deux sommeils', "# Deux sommeils\n\nUn **gras** et un [lien](https://exemple.fr).");

        $crawler = $client->request('GET', '/notes/'.$noteId);
        $preview = $crawler->filter('.fx-app__pane--preview');

        self::assertCount(1, $preview);
        self::assertStringContainsString('Deux sommeils', $preview->text());
        self::assertStringNotContainsString(
            '**',
            $preview->text(),
            'L\'aperçu montre la note sans ses marques : c\'est toute sa raison d\'être.',
        );
        self::assertSame('https://exemple.fr', $preview->filter('a.fx-prose__link')->attr('href'));
    }

    public function testTheSaveEndpointReturnsTheRefreshedPreview(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Deux sommeils');

        $crawler = $client->request('GET', '/notes/'.$noteId);
        $token = (string) $crawler->filter('.fx-note__editor')->attr('data-note-editor-token-value');

        $client->request(
            'POST',
            '/notes/'.$noteId.'/corps',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: json_encode(['body' => '## Nouveau titre'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful((string) $client->getResponse()->getContent());
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertIsString($payload['preview']);
        self::assertStringContainsString('fx-prose__line--h2', $payload['preview']);
        self::assertStringContainsString('Nouveau titre', $payload['preview']);
    }

    public function testTurningThePreviewOffRemovesThePane(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $noteId = $this->writeNote($client, 'Deux sommeils');

        $crawler = $client->request('GET', '/reglages');
        // Ancré sur son formulaire : la page en porte plusieurs, et compter
        // les positions ferait échouer ce test au prochain réglage ajouté.
        $token = (string) $crawler->filter('form[action="/reglages/affichage"] input[name="_token"]')
            ->first()
            ->attr('value');
        $client->request('POST', '/reglages/affichage', ['_token' => $token, 'previewPane' => '0']);

        $crawler = $client->request('GET', '/notes/'.$noteId);

        self::assertCount(0, $crawler->filter('.fx-app__pane--preview'));
    }

    public function testANoteOfAnotherAccountIsNotFound(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'alice@focusyn.fr');
        $foreignId = $this->writeNote($client, 'Secret industriel');

        // Bob, autre compte, autre organisation, connaît l'adresse exacte.
        $this->signOut($client);
        $this->logIn($client, 'bob@focusyn.fr');
        $client->request('GET', '/notes/'.$foreignId);

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $client->getResponse()->getStatusCode(),
            'On répond 404 et non 403 : un 403 confirmerait que la note existe.',
        );
    }

    public function testSearchNeverReachesAnotherAccountNotes(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'alice@focusyn.fr');
        $this->writeNote($client, 'Secret industriel', 'Formule confidentielle.');

        $this->signOut($client);
        $this->logIn($client, 'bob@focusyn.fr');
        $crawler = $client->request('GET', '/recherche?query=confidentielle');

        self::assertCount(0, $crawler->filter('.fx-note-row'));
    }
}
