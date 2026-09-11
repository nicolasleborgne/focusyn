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

    /**
     * Le formulaire est public et fait partir un courriel vers une adresse
     * nommée dans la requête : sans jeton, n'importe quel site pouvait
     * déclencher l'envoi depuis le navigateur d'un visiteur.
     */
    public function testAPostWithoutATokenSendsNothing(): void
    {
        $client = self::createClient();
        $this->createAccount();

        $client->request('POST', '/mot-de-passe-oublie', ['email' => self::EMAIL]);

        // Le jeton manquant est une exception de sécurité : le pare-feu
        // renvoie vers la connexion. Ce qui compte est qu'aucun courriel ne
        // soit parti.
        self::assertResponseRedirects();
        self::assertEmailCount(0);
    }

    /**
     * La boîte visée a sa propre mesure, et l'écran n'en dit rien : la réponse
     * ne doit pas dépendre de ce que l'on sait de l'adresse. Trois liens par
     * heure suffisent à qui a vraiment perdu son mot de passe ; au-delà, c'est
     * la boîte de quelqu'un qu'on remplit.
     */
    public function testTheSameMailboxCannotBeFloodedWithLinks(): void
    {
        $client = self::createClient();
        // Le compteur vit en mémoire dans le noyau : le laisser redémarrer à
        // chaque requête reviendrait à repartir de zéro à chaque envoi.
        $client->disableReboot();
        $this->createAccount();

        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $crawler = $client->request('GET', '/mot-de-passe-oublie');
            $crawler = $client->submit($crawler->selectButton('Envoyer le lien')->form(['email' => self::EMAIL]));

            // Le même message à chaque fois, y compris au quatrième : l'écran
            // ne laisse pas deviner ce qu'il sait de l'adresse.
            self::assertStringContainsString('Si un compte existe pour cette adresse', $crawler->text());

            // Le décompte porte sur la dernière requête. Trois liens partent,
            // le quatrième non.
            self::assertEmailCount($attempt <= 3 ? 1 : 0);
        }
    }

    /**
     * L'autre moitié de la mesure : celle qui arrête l'auteur, et le lui dit.
     * Des adresses toutes différentes pour n'éprouver que la limite par
     * appareil — autrement c'est celle de la boîte qui répondrait d'abord.
     */
    public function testTooManyRequestsFromOneDeviceAreRefusedOutLoud(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $crawler = $client->request('GET', '/mot-de-passe-oublie');
            $client->submit($crawler->selectButton('Envoyer le lien')->form([
                'email' => \sprintf('inconnu%d@focusyn.fr', $attempt),
            ]));
            self::assertResponseIsSuccessful();
        }

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $crawler = $client->submit($crawler->selectButton('Envoyer le lien')->form([
            'email' => 'inconnu6@focusyn.fr',
        ]));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Trop de demandes', $crawler->text());
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
