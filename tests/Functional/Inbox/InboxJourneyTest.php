<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inbox;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le sas : ce qui entre, et les trois manières d'en sortir.
 *
 * Une capture ne survit à aucune des trois — c'est ce qui distingue une boîte
 * d'une liste.
 */
final class InboxJourneyTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testAnEmptyInboxSaysSoRatherThanShowingNothing(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/boite');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Tout est class', $crawler->text());
        // Rien à trier : le compteur de la navigation se tait plutôt que
        // d'afficher un zéro.
        self::assertCount(0, $crawler->filter('a[href="/boite"] .fx-nav-item__count'));
    }

    public function testWhatIsCapturedShowsUpToBeSorted(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $this->capture($client, "Le ratio n'est qu'une variable\nMouture, temps, agitation.");

        self::assertStringContainsString("Le ratio n'est qu'une variable", $crawler->filter('.fx-capture-row__title')->text());
        self::assertStringContainsString('Mouture, temps', $crawler->filter('.fx-capture-row__excerpt')->text());
        self::assertSame('1', $crawler->filter('a[href="/boite"] .fx-nav-item__count')->text());
    }

    public function testAnAddressAloneIsLabelledALink(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $this->capture($client, 'https://exemple.fr/ekirch');

        self::assertStringContainsString('Lien', $crawler->filter('.fx-capture-row .fx-tag')->text());
        // Le corps ne répète pas le titre : l'adresse est déjà au-dessus.
        self::assertCount(0, $crawler->filter('.fx-capture-row__excerpt'));
    }

    public function testFilingAsANoteOpensTheNoteAndEmptiesTheBox(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->capture($client, "Deux sommeils\n\nLa nuit se coupait en deux.");

        $crawler = $this->file($client, 'note');

        // On arrive dans la note, avec ce qui a été capturé.
        self::assertStringContainsString('Deux sommeils', $crawler->filter('.fx-note__title')->text());
        self::assertStringContainsString('La nuit se coupait en deux.', $crawler->text());
        self::assertStringContainsString('Tout est class', $client->request('GET', '/boite')->text());
    }

    public function testFilingAsATaskPutsTheTitleInTheChosenList(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openList($client);
        $this->capture($client, "Relire Ekirch, chapitre 8\n\nEt croiser avec le carnet de 3 mois.");

        $crawler = $this->file($client, 'task');

        self::assertStringContainsString('Ajouté à la liste', $crawler->filter('.fx-settings__notice')->text());
        // C'est le titre qui devient la tâche : une tâche se lit d'une ligne.
        self::assertStringContainsString('Relire Ekirch, chapitre 8', $client->request('GET', '/taches')->text());
        self::assertStringContainsString('Tout est class', $client->request('GET', '/boite')->text());
    }

    public function testWithoutAnyListTheTaskRouteIsNotEvenOffered(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $this->capture($client, 'Une idée sans liste où la ranger');

        // Proposer un classement qui ne peut aboutir nulle part serait pire
        // que de ne rien proposer.
        self::assertCount(0, $crawler->filter('.fx-capture-row__actions select'));
        self::assertCount(0, $crawler->filter('.fx-capture-row__actions button[name="task"]'));
    }

    public function testDiscardingLeavesNothingBehind(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->capture($client, 'Une référence repérée en passant');

        $crawler = $this->file($client, 'discard');

        self::assertStringContainsString('écarté', $crawler->filter('.fx-settings__notice')->text());
        self::assertCount(0, $crawler->filter('.fx-capture-row'));
    }

    public function testSortingTwiceChangesNothingRatherThanFailing(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->capture($client, 'Une référence repérée en passant');

        $id = $this->firstCaptureId($client);
        $token = $this->tokenFor($client, '/boite/'.$id.'/classer');

        $client->request('POST', '/boite/'.$id.'/classer', ['_token' => $token, 'discard' => '1']);
        $client->followRedirect();

        // Le second envoi porte sur ce qui n'existe plus : deux clics, un
        // aller-retour depuis un autre appareil. Rien à signaler.
        $client->request('POST', '/boite/'.$id.'/classer', ['_token' => $token, 'discard' => '1']);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
    }

    public function testASharedPageArrivesAsALinkWithoutAnyToken(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        // Ce que poste le système depuis la feuille de partage : pas de jeton,
        // il n'a pas vu notre page. Le titre d'abord, l'adresse ensuite.
        $client->request('POST', '/partage', [
            'title' => 'Segmented sleep in pre-industrial Europe',
            'url' => 'https://exemple.fr/ekirch',
        ]);

        self::assertResponseStatusCodeSame(303);
        $crawler = $client->followRedirect();

        self::assertStringContainsString('Segmented sleep', $crawler->filter('.fx-capture-row__title')->text());
        self::assertStringContainsString('Lien', $crawler->filter('.fx-capture-row .fx-tag')->text());
        self::assertStringContainsString('Partagé depuis', $crawler->filter('.fx-capture-row__meta')->text());
    }

    public function testASharedAddressIsNotStoredTwice(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        // Un navigateur envoie souvent l'adresse à la fois comme texte et
        // comme adresse : la répéter dans le corps n'apprendrait rien.
        $client->request('POST', '/partage', [
            'text' => 'https://exemple.fr/ekirch',
            'url' => 'https://exemple.fr/ekirch',
        ]);
        $crawler = $client->followRedirect();

        self::assertSame('https://exemple.fr/ekirch', $crawler->filter('.fx-capture-row__title')->text());
        self::assertCount(0, $crawler->filter('.fx-capture-row__excerpt'));
    }

    public function testAnEmptyShareOpensTheBoxWithoutAddingAnything(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $client->request('POST', '/partage', ['title' => '  ']);
        $crawler = $client->followRedirect();

        self::assertStringContainsString('Tout est class', $crawler->text());
    }

    private function capture(KernelBrowser $client, string $text): Crawler
    {
        $client->request('POST', '/boite/capturer', [
            '_token' => $this->tokenFor($client, '/boite/capturer'),
            'text' => $text,
        ]);

        return $client->followRedirect();
    }

    /** @param 'note'|'task'|'discard' $action */
    private function file(KernelBrowser $client, string $action): Crawler
    {
        $id = $this->firstCaptureId($client);
        $fields = ['_token' => $this->tokenFor($client, '/boite/'.$id.'/classer'), $action => '1'];

        if ('task' === $action) {
            $fields['taskList'] = (string) $client->request('GET', '/boite')
                ->filter('.fx-capture-row__actions option')
                ->first()
                ->attr('value');
        }

        $client->request('POST', '/boite/'.$id.'/classer', $fields);

        return $client->followRedirect();
    }

    private function firstCaptureId(KernelBrowser $client): string
    {
        $action = (string) $client->request('GET', '/boite')
            ->filter('.fx-capture-row__actions')
            ->first()
            ->attr('action');

        return explode('/', $action)[2];
    }

    private function openList(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/taches');
        $client->submit($crawler->filter('form[action="/taches/nouvelle"]')->form());
        $client->followRedirect();
    }

    private function tokenFor(KernelBrowser $client, string $action): string
    {
        return (string) $client->request('GET', '/boite')
            ->filter('form[action="'.$action.'"] input[name="_token"]')
            ->first()
            ->attr('value');
    }
}
