<?php

declare(strict_types=1);

namespace App\Tests\Functional\Privacy;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le RGPD vu depuis l'écran des réglages : consentir, retirer, emporter,
 * effacer.
 *
 * Ces tests valent moins pour le code que pour la promesse : ils vérifient
 * qu'un refus est bien un refus par défaut, et qu'un effacement efface.
 */
final class PersonalDataTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testNothingIsConsentedToWhenAnAccountIsCreated(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/reglages');

        self::assertCount(3, $crawler->filter('form[action="/reglages/donnees"] .fx-switch'));
        self::assertCount(0, $crawler->filter('form[action="/reglages/donnees"] .fx-switch--on'));
    }

    public function testGrantingThenWithdrawingAConsent(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, ['consent' => 'assistant', 'granted' => '1']);
        self::assertTrue($this->allows($client, 'assistant'));

        // Les autres n'ont pas suivi : un consentement se donne un par un.
        self::assertFalse($this->allows($client, 'usage'));
        self::assertFalse($this->allows($client, 'backup'));

        $this->adjust($client, ['consent' => 'assistant', 'granted' => '0']);
        self::assertFalse($this->allows($client, 'assistant'));
    }

    public function testChoosingARetentionPeriod(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, ['retention' => 'unlimited']);

        $selected = $client->request('GET', '/reglages')
            ->filter('form[action="/reglages/donnees"] .fx-pill--selected');

        self::assertCount(1, $selected);
        self::assertStringContainsString('Sans limite', $selected->text());
    }

    public function testAnUnknownChoiceChangesNothing(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, ['consent' => 'revente-a-des-tiers', 'granted' => '1']);

        self::assertCount(
            0,
            $client->request('GET', '/reglages')->filter('form[action="/reglages/donnees"] .fx-switch--on'),
        );
    }

    public function testTheJsonExportCarriesEverythingTheAccountWrote(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Le sommeil comme sujet', 'Trois nuits courtes de suite.');
        $this->openList($client, 'Cette semaine');

        $client->request('GET', '/reglages/donnees/export.json');
        $response = $client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('focusyn-export.json', (string) $response->headers->get('Content-Disposition'));

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('nicolas@focusyn.fr', $data['compte']);
        self::assertSame('Le sommeil comme sujet', $data['notes'][0]['titre']);
        self::assertSame('Trois nuits courtes de suite.', $data['notes'][0]['corps']);
        self::assertSame('Cette semaine', $data['taches'][0]['nom']);
    }

    public function testTheMarkdownExportIsReadableWithoutFocusyn(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Le sommeil comme sujet', 'Trois nuits courtes de suite.');

        $client->request('GET', '/reglages/donnees/export.md');
        $body = (string) $client->getResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/markdown', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('### Le sommeil comme sujet', $body);
        self::assertStringContainsString('Trois nuits courtes de suite.', $body);
    }

    public function testTheExportOnlyCarriesTheAccountOwnData(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'autre@focusyn.fr');
        $this->writeNote($client, 'Note de quelqu’un d’autre');
        $this->signOut($client);

        $this->logIn($client);
        $client->request('GET', '/reglages/donnees/export.json');

        self::assertStringNotContainsString('quelqu', (string) $client->getResponse()->getContent());
    }

    public function testErasureTakesTwoSubmissions(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->writeNote($client, 'Le sommeil comme sujet');
        $this->openList($client, 'Cette semaine');

        // Premier envoi : on arme, on n'efface rien.
        $this->erase($client);
        self::assertStringContainsString('Le sommeil comme sujet', $client->request('GET', '/bibliotheque')->text());

        // Le bouton dit maintenant ce qu'il fera.
        $button = $client->request('GET', '/reglages')->filter('.fx-button--danger');
        self::assertStringContainsString('is-armed', (string) $button->attr('class'));
        self::assertStringContainsString('Confirmer', $button->text());

        // Second envoi : cette fois, tout part.
        $this->erase($client);
        self::assertStringNotContainsString('Le sommeil comme sujet', $client->request('GET', '/bibliotheque')->text());
        self::assertStringNotContainsString('Cette semaine', $client->request('GET', '/taches')->text());

        // Et le compte, lui, est toujours là.
        self::assertResponseIsSuccessful();
        $client->request('GET', '/reglages');
        self::assertResponseIsSuccessful();
    }

    public function testErasureLeavesOtherAccountsAlone(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'autre@focusyn.fr');
        $this->writeNote($client, 'Note de quelqu’un d’autre');
        $this->signOut($client);

        $this->logIn($client);
        $this->writeNote($client, 'Le sommeil comme sujet');
        $this->erase($client);
        $this->erase($client);
        $this->signOut($client);

        $this->logIn($client, 'autre@focusyn.fr');
        self::assertStringContainsString('Note de quelqu', $client->request('GET', '/bibliotheque')->text());
    }

    public function testTheSectionIsClosedToVisitors(): void
    {
        $client = self::createClient();

        $client->request('GET', '/reglages/donnees/export.json');
        self::assertResponseRedirects('/connexion');

        $client->request('POST', '/reglages/donnees');
        self::assertResponseRedirects('/connexion');
    }

    public function testAConsentCannotBeChangedWithoutTheFormToken(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $client->request('POST', '/reglages/donnees', ['consent' => 'usage', 'granted' => '1']);

        // Un jeton absent est traité comme une session à rétablir, pas comme un
        // choix de l'utilisateur : la demande est écartée avant le contrôleur.
        self::assertResponseRedirects('/connexion');
        self::assertFalse($this->allows($client, 'usage'));
    }

    /** @param array<string, string> $values */
    private function adjust(KernelBrowser $client, array $values): void
    {
        $client->request('POST', '/reglages/donnees', [
            ...$values,
            '_token' => $this->token($client, '/reglages/donnees'),
        ]);
        $client->followRedirect();
    }

    private function erase(KernelBrowser $client): void
    {
        $client->request('POST', '/reglages/donnees/effacer', [
            '_token' => $this->token($client, '/reglages/donnees/effacer'),
        ]);
        $client->followRedirect();
    }

    /**
     * Le jeton se lit dans le gabarit plutôt que dans le conteneur : hors
     * requête, le magasin de session n'existe pas, et un jeton fabriqué à côté
     * ne prouverait pas que le formulaire en porte un.
     */
    private function token(KernelBrowser $client, string $action): string
    {
        return (string) $client->request('GET', '/reglages')
            ->filter('form[action="'.$action.'"] input[name="_token"]')
            ->first()
            ->attr('value');
    }

    private function allows(KernelBrowser $client, string $consent): bool
    {
        return 1 === $client->request('GET', '/reglages')
            ->filterXPath(\sprintf(
                '//form[.//input[@value="%s"]]//button[contains(@class, "fx-switch--on")]',
                $consent,
            ))
            ->count();
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
}
