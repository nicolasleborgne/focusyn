<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Shared\Application\Command\CommandBus;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le parcours complet du second facteur, tel qu'un utilisateur le vit :
 * activation depuis les réglages, puis connexion en deux temps.
 */
final class TwoFactorJourneyTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const string EMAIL = 'nicolas@focusyn.fr';
    private const string PASSWORD = 'une phrase de passe tenable';

    public function testTheSettingsScreenShowsTheSecondFactorAsOffByDefault(): void
    {
        $client = self::createClient();
        $this->signIn($client);

        $crawler = $client->request('GET', '/reglages');

        self::assertResponseIsSuccessful();
        // Le second facteur est rangé sous « Compte », avec le mot de passe :
        // ce sont les clés de la même porte.
        self::assertStringContainsString('Activer', $crawler->filter('.fx-settings__section')->first()->text());
    }

    public function testEnrolmentOffersBothAQrCodeAndAManualKey(): void
    {
        $client = self::createClient();
        $this->signIn($client);

        $crawler = $client->request('GET', '/reglages/double-authentification');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.fx-enrolment__qr'));
        self::assertMatchesRegularExpression(
            '/^[A-Z2-7]{4}( [A-Z2-7]{4})+$/',
            trim($crawler->filter('.fx-enrolment__secret')->text()),
        );
    }

    public function testEnablingTheSecondFactorShowsTheBackupCodesOnce(): void
    {
        $client = self::createClient();
        $this->signIn($client);

        $crawler = $this->enable($client);

        self::assertCount(3, $crawler->filter('.fx-settings__code'));
        self::assertStringContainsString('Double authentification active', $crawler->text());

        // Rechargement : les codes ont disparu, comme annoncé à l'écran.
        $crawler = $client->request('GET', '/reglages');
        self::assertCount(0, $crawler->filter('.fx-settings__code'));
    }

    public function testAWrongCodeDuringEnrolmentSendsBackToTheForm(): void
    {
        $client = self::createClient();
        $this->signIn($client);
        $client->request('GET', '/reglages/double-authentification');

        $crawler = $client->request('GET', '/reglages/double-authentification');
        $client->submit($crawler->filter('.fx-enrolment__form')->form(['code' => '000000']));

        self::assertResponseRedirects('/reglages/double-authentification');
        $crawler = $client->followRedirect();
        self::assertStringContainsString('Ce code ne correspond pas', $crawler->text());
    }

    public function testOnceEnabledSigningInStopsAtTheSecondFactor(): void
    {
        $client = self::createClient();
        $this->signIn($client);
        $this->enable($client);
        $client->request('POST', '/deconnexion');

        // La connexion renvoie vers la page d'accueil, qui rebondit aussitôt
        // sur l'écran du second facteur : deux redirections, à suivre jusqu'au
        // bout comme le ferait un navigateur.
        $client->followRedirects();
        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['login[email]'] = self::EMAIL;
        $form['login[password]'] = self::PASSWORD;
        $client->submit($form);

        self::assertRouteSame('two_factor_login');
        self::assertStringContainsString('Vérification en deux étapes', $client->getCrawler()->text());
    }

    public function testDisablingReturnsToASinglePasswordStep(): void
    {
        $client = self::createClient();
        $this->signIn($client);
        $this->enable($client);

        $crawler = $client->request('GET', '/reglages');
        $client->submit($crawler->filter('form[action="/reglages/double-authentification/desactiver"]')->form());
        $client->followRedirect();

        $client->request('POST', '/deconnexion');
        $client->followRedirects();
        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['login[email]'] = self::EMAIL;
        $form['login[password]'] = self::PASSWORD;
        $client->submit($form);

        self::assertRouteSame('home');
    }

    private function enable(KernelBrowser $client): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $client->request('GET', '/reglages/double-authentification');
        $secret = str_replace(' ', '', trim($crawler->filter('.fx-enrolment__secret')->text()));
        self::assertNotSame('', $secret);

        $client->submit($crawler->filter('.fx-enrolment__form')->form([
            'code' => TOTP::createFromSecret($secret)->now(),
        ]));

        self::assertResponseRedirects('/reglages');

        return $client->followRedirect();
    }

    private function signIn(KernelBrowser $client): void
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);
        $bus->dispatch(new RegisterUser(self::EMAIL, self::PASSWORD));

        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['login[email]'] = self::EMAIL;
        $form['login[password]'] = self::PASSWORD;
        $client->submit($form);
        $client->followRedirect();
    }
}
