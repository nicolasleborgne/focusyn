<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Identity\Application\Port\PasswordResetLink;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Réinitialisation par lien signé : aucun jeton n'est stocké, le lien porte sa
 * propre preuve et se périme de deux façons.
 */
final class PasswordResetTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const string EMAIL = 'nicolas@focusyn.fr';
    private const string PASSWORD = 'une phrase de passe tenable';
    private const string NEW_PASSWORD = 'une toute autre phrase';

    public function testTheSignInScreenOffersTheRecourse(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');

        $link = $crawler->filter('.fx-auth__recourse a')->first();

        self::assertSame('Mot de passe oublié', trim($link->text()));
        self::assertSame('/mot-de-passe-oublie', $link->attr('href'));
    }

    public function testAskingForALinkSendsOne(): void
    {
        $client = self::createClient();
        $this->createAccount();

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $client->submit($crawler->selectButton('Envoyer le lien')->form(['email' => self::EMAIL]));

        self::assertResponseIsSuccessful();
        self::assertThat($client->getProfile(), self::logicalNot(self::isFalse()));
        self::assertEmailCount(1);
    }

    public function testAnUnknownAddressGetsTheSameAnswerAndNoMail(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $crawler = $client->submit($crawler->selectButton('Envoyer le lien')->form(['email' => 'personne@focusyn.fr']));

        // Même réponse que pour un compte existant : ce formulaire ne doit pas
        // permettre de savoir qui est inscrit.
        self::assertStringContainsString('Si un compte existe pour cette adresse', $crawler->text());
        self::assertEmailCount(0);
    }

    public function testTheLinkLetsTheAccountChooseANewPassword(): void
    {
        $client = self::createClient();
        $this->createAccount();

        $crawler = $client->request('GET', $this->resetUrl());
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Enregistrer et se connecter')->form(['password' => self::NEW_PASSWORD]));
        self::assertResponseRedirects('/connexion');

        // Le nouveau mot de passe ouvre bien la session.
        $crawler = $client->followRedirect();
        $form = $crawler->selectButton('Se connecter')->form();
        $form['login[email]'] = self::EMAIL;
        $form['login[password]'] = self::NEW_PASSWORD;
        $client->submit($form);

        self::assertResponseRedirects('/');
    }

    public function testATooShortPasswordIsRefused(): void
    {
        $client = self::createClient();
        $this->createAccount();

        $crawler = $client->request('GET', $this->resetUrl());
        $crawler = $client->submit($crawler->selectButton('Enregistrer et se connecter')->form(['password' => 'court']));

        self::assertStringContainsString('Douze caractères au minimum', $crawler->text());
    }

    public function testALinkCannotServeTwice(): void
    {
        $client = self::createClient();
        $this->createAccount();
        $url = $this->resetUrl();

        $crawler = $client->request('GET', $url);
        $client->submit($crawler->selectButton('Enregistrer et se connecter')->form(['password' => self::NEW_PASSWORD]));

        $crawler = $client->request('GET', $url);

        self::assertSame(
            Response::HTTP_GONE,
            $client->getResponse()->getStatusCode(),
            'L\'empreinte du mot de passe fait partie du lien : le changer suffit à le périmer.',
        );
        self::assertStringContainsString('n\'est plus valable', $crawler->text());
    }

    public function testATamperedLinkIsRefused(): void
    {
        $client = self::createClient();
        $this->createAccount();

        $crawler = $client->request('GET', $this->resetUrl().'x');

        self::assertSame(Response::HTTP_GONE, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('n\'est plus valable', $crawler->text());
    }

    private function createAccount(): void
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);
        $bus->dispatch(new RegisterUser(self::EMAIL, self::PASSWORD));
    }

    private function resetUrl(): string
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString(self::EMAIL));
        self::assertNotNull($user);

        $links = self::getContainer()->get(PasswordResetLink::class);
        self::assertInstanceOf(PasswordResetLink::class, $links);

        return $links->urlFor($user);
    }
}
