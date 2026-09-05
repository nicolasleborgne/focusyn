<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

/**
 * Le parcours réel : formulaires remplis, pare-feu traversé, session posée.
 */
final class AuthenticationTest extends WebTestCase
{
    use Factories;

    private const string PASSWORD = 'une phrase de passe tenable';

    public function testAnAnonymousVisitorIsSentToTheSignInScreen(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testTheSignInScreenIsPublic(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="login[email]"]'));
        self::assertCount(1, $crawler->filter('input[name="login[password]"]'));
    }

    public function testTheProbeAndTheOfflinePageStayPublic(): void
    {
        $client = self::createClient();

        $client->request('GET', '/healthz');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/offline');
        self::assertResponseIsSuccessful();
    }

    public function testSigningUpOpensTheApplicationStraightAway(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/inscription');

        $form = $crawler->selectButton('Créer le compte')->form();
        $form['registration_form[email]'] = 'nouvelle@focusyn.fr';
        $form['registration_form[plainPassword]'] = self::PASSWORD;
        $client->submit($form);

        self::assertResponseRedirects('/');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.fx-sidebar__account', 'nouvelle@focusyn.fr');
    }

    public function testSigningUpTwiceWithTheSameAddressIsRefused(): void
    {
        $client = self::createClient();
        $this->createAccount('occupee@focusyn.fr');

        $crawler = $client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer le compte')->form();
        $form['registration_form[email]'] = 'occupee@focusyn.fr';
        $form['registration_form[plainPassword]'] = self::PASSWORD;
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Un compte existe déjà pour cette adresse.', $crawler->text());
    }

    public function testAShortPasswordIsRefusedBeforeReachingTheDomain(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/inscription');

        $form = $crawler->selectButton('Créer le compte')->form();
        $form['registration_form[email]'] = 'courte@focusyn.fr';
        $form['registration_form[plainPassword]'] = 'trop court';
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Douze caractères au minimum.', $crawler->text());
    }

    public function testCorrectCredentialsOpenASession(): void
    {
        $client = self::createClient();
        $this->createAccount('nicolas@focusyn.fr');

        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['login[email]'] = 'nicolas@focusyn.fr';
        $form['login[password]'] = self::PASSWORD;
        $client->submit($form);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('.fx-sidebar__account', 'nicolas@focusyn.fr');
    }

    public function testAWrongPasswordKeepsTheDoorClosed(): void
    {
        $client = self::createClient();
        $this->createAccount('nicolas@focusyn.fr');

        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['login[email]'] = 'nicolas@focusyn.fr';
        $form['login[password]'] = 'ce n\'est pas le bon';
        $client->submit($form);

        $crawler = $client->followRedirect();

        self::assertCount(1, $crawler->filter('.fx-auth__message--error'));
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testSigningOutClosesTheSession(): void
    {
        $client = self::createClient();
        $this->createAccount('nicolas@focusyn.fr');
        $this->signIn($client, 'nicolas@focusyn.fr');

        $client->request('POST', '/deconnexion');
        self::assertResponseRedirects();

        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testAnAuthenticatedVisitorIsSentAwayFromTheSignInScreen(): void
    {
        $client = self::createClient();
        $this->createAccount('nicolas@focusyn.fr');
        $this->signIn($client, 'nicolas@focusyn.fr');

        $client->request('GET', '/connexion');

        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode());
    }

    /**
     * Crée un compte par le cas d'usage plutôt que par le formulaire : les
     * tests qui ont besoin d'un compte existant ne doivent pas dépendre du
     * balisage de l'écran d'inscription.
     */
    private function createAccount(string $email): void
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        $bus->dispatch(new RegisterUser($email, self::PASSWORD));
    }

    private function signIn(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['login[email]'] = $email;
        $form['login[password]'] = self::PASSWORD;
        $client->submit($form);
        $client->followRedirect();
    }
}
