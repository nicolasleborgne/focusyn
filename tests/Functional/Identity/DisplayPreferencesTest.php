<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Les réglages d'affichage ne recalculent aucun style : ils posent des
 * attributs sur `<html>`, que le design system interprète.
 */
final class DisplayPreferencesTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testANewAccountGetsTheDesignDefaults(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/');
        $html = $crawler->filter('html');

        self::assertSame('slate', $html->attr('data-fx-accent'));
        self::assertSame('serif', $html->attr('data-fx-prose'));
        self::assertSame('comfortable', $html->attr('data-fx-density'));
        // Rien n'a été choisi : le thème suit le système, et c'est la feuille
        // de style qui en tire les conséquences.
        self::assertSame('system', $html->attr('data-fx-theme'));
        self::assertStringContainsString('--fx-markdown-mark-opacity: 0.45', (string) $html->attr('style'));
    }

    public function testChangingTheAccentIsReflectedEverywhere(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, 'accent', 'brick');

        // Sur l'écran des réglages, mais aussi sur tous les autres : l'attribut
        // est posé par le gabarit de base.
        foreach (['/reglages', '/', '/bibliotheque'] as $uri) {
            $crawler = $client->request('GET', $uri);
            self::assertSame('brick', $crawler->filter('html')->attr('data-fx-accent'), $uri);
        }
    }

    public function testTheChosenAccentIsMarkedInTheSettings(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->adjust($client, 'accent', 'olive');

        $crawler = $client->request('GET', '/reglages');

        self::assertCount(1, $crawler->filter('.fx-accents__dot--current'));
        self::assertSame('true', $crawler->filter('.fx-accents__dot--current')->attr('aria-current'));
    }

    public function testTheProseFontTogglesBothWays(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, 'proseFont', 'sans');
        self::assertSame('sans', $client->request('GET', '/')->filter('html')->attr('data-fx-prose'));

        $this->adjust($client, 'proseFont', 'serif');
        self::assertSame('serif', $client->request('GET', '/')->filter('html')->attr('data-fx-prose'));
    }

    public function testTheThemeSwitchesBothWaysAndBackToTheSystem(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, 'theme', 'dark');
        self::assertSame('dark', $client->request('GET', '/')->filter('html')->attr('data-fx-theme'));

        $this->adjust($client, 'theme', 'light');
        self::assertSame('light', $client->request('GET', '/')->filter('html')->attr('data-fx-theme'));

        // Et l'on peut s'en remettre au système de nouveau : c'est un choix
        // comme les deux autres, pas seulement l'état initial.
        $this->adjust($client, 'theme', 'system');
        self::assertSame('system', $client->request('GET', '/')->filter('html')->attr('data-fx-theme'));
    }

    public function testTheThemeIsOfferedAtTheHeadOfTheEditorSection(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/reglages');
        $editor = $crawler->filter('.fx-settings__section')->reduce(
            static fn ($section): bool => str_contains($section->text(), 'Éditeur'),
        );

        self::assertCount(1, $editor);
        self::assertStringContainsString(
            'Thème sombre',
            $editor->filter('.fx-settings__row')->first()->text(),
            'Le commutateur ouvre la section Éditeur, comme dans la maquette.',
        );
    }

    public function testTheMarkOpacityFollowsTheOfferedSteps(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, 'markOpacity', '0.8');

        self::assertStringContainsString(
            '--fx-markdown-mark-opacity: 0.8',
            (string) $client->request('GET', '/')->filter('html')->attr('style'),
        );
    }

    public function testAValueOutsideTheOfferedChoicesChangesNothing(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->adjust($client, 'markOpacity', '0.61');
        $this->adjust($client, 'accent', 'fuchsia');

        $this->adjust($client, 'theme', 'sépia');

        $html = $client->request('GET', '/')->filter('html');
        self::assertSame('slate', $html->attr('data-fx-accent'));
        self::assertSame('system', $html->attr('data-fx-theme'));
        self::assertStringContainsString('--fx-markdown-mark-opacity: 0.45', (string) $html->attr('style'));
    }

    public function testThePreferencesSurviveASignOut(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'nicolas@focusyn.fr');
        $this->adjust($client, 'density', 'compact');

        $this->signOut($client);
        $crawler = $client->request('GET', '/connexion');

        // Hors session, les valeurs d'origine s'appliquent…
        self::assertSame('comfortable', $crawler->filter('html')->attr('data-fx-density'));
    }

    public function testAnAnonymousVisitorSeesTheDesignDefaults(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/connexion');

        self::assertSame('slate', $crawler->filter('html')->attr('data-fx-accent'));
        // Un visiteur n'a rien réglé : son système décide, comme pour un compte
        // qui n'a pas choisi.
        self::assertSame('system', $crawler->filter('html')->attr('data-fx-theme'));
    }

    private function adjust(KernelBrowser $client, string $field, string $value): void
    {
        $crawler = $client->request('GET', '/reglages');
        // Ancré sur son formulaire : la page en porte plusieurs, et compter
        // les positions ferait échouer ce test au prochain réglage ajouté.
        $token = (string) $crawler->filter('form[action="/reglages/affichage"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/reglages/affichage', ['_token' => $token, $field => $value]);
        $client->followRedirect();
    }
}
