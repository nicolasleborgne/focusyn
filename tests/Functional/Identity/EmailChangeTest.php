<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Changer d'adresse de connexion.
 *
 * Rien ne change à l'envoi : la nouvelle adresse attend d'être confirmée depuis
 * sa propre boîte. Ce sont ces deux temps que les tests vérifient — plus
 * l'avertissement envoyé à l'ancienne, qui est la seule alerte du propriétaire
 * légitime si sa session a été dérobée.
 */
final class EmailChangeTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testAskingChangesNothingYetAndWritesToBothAddresses(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->request($client, 'neuve@focusyn.fr');

        // Deux : le lien vers la nouvelle adresse, l'avertissement vers
        // l'ancienne.
        self::assertEmailCount(2);
        self::assertNotNull($this->userOf('nicolas@focusyn.fr'), 'L\'ancienne adresse vaut toujours.');
        self::assertNull($this->userOf('neuve@focusyn.fr'));

        $crawler = $client->followRedirect();
        self::assertStringContainsString('En attente de confirmation', $crawler->text());
    }

    public function testTheWarningGoesToThePreviousAddress(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->request($client, 'neuve@focusyn.fr');

        $warning = self::getMailerMessage(0);
        $confirmation = self::getMailerMessage(1);
        self::assertInstanceOf(Email::class, $warning);
        self::assertInstanceOf(Email::class, $confirmation);

        $recipients = array_merge(
            array_map(static fn ($a) => $a->getAddress(), $warning->getTo()),
            array_map(static fn ($a) => $a->getAddress(), $confirmation->getTo()),
        );

        self::assertContains('neuve@focusyn.fr', $recipients);
        self::assertContains('nicolas@focusyn.fr', $recipients);
    }

    public function testFollowingTheLinkChangesTheAddressAndKeepsYouSignedIn(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->request($client, 'neuve@focusyn.fr');

        $client->request('GET', $this->linkFromMail());
        $crawler = $client->followRedirect();

        self::assertNotNull($this->userOf('neuve@focusyn.fr'));
        self::assertNull($this->userOf('nicolas@focusyn.fr'));
        // L'identifiant de connexion *est* l'adresse : sans réouverture de
        // session, confirmer déconnecterait.
        self::assertStringContainsString('neuve@focusyn.fr', $crawler->text());
    }

    public function testALinkStopsWorkingOnceTheRequestIsCalledOff(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->request($client, 'neuve@focusyn.fr');
        $link = $this->linkFromMail();

        // On sélectionne le bouton, pas le formulaire : sinon sa valeur
        // `cancel` ne serait pas envoyée, et l'annulation n'aurait pas lieu.
        $crawler = $client->request('GET', '/reglages');
        $client->submit($crawler->filterXPath('//button[@name="cancel"]')->form());
        $client->followRedirect();

        $client->request('GET', $link);

        self::assertResponseStatusCodeSame(410);
        self::assertNull($this->userOf('neuve@focusyn.fr'));
    }

    public function testAskingAgainInvalidatesThePreviousLink(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->request($client, 'neuve@focusyn.fr');
        $first = $this->linkFromMail();

        $this->request($client, 'encore@focusyn.fr');

        $client->request('GET', $first);

        self::assertResponseStatusCodeSame(410);
    }

    public function testAnAddressAlreadyInUseIsRefusedAndNothingIsSent(): void
    {
        $client = self::createClient();
        $this->logIn($client, 'occupee@focusyn.fr');
        $this->signOut($client);
        $this->logIn($client);

        $this->request($client, 'occupee@focusyn.fr');

        self::assertEmailCount(0);
        self::assertStringContainsString('déjà celle d\'un autre compte', $client->followRedirect()->text());
    }

    private function request(KernelBrowser $client, string $email): void
    {
        $token = (string) $client->request('GET', '/reglages')
            ->filter('form[action="/reglages/adresse"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/reglages/adresse', ['_token' => $token, 'email' => $email]);
    }

    private function linkFromMail(): string
    {
        foreach ([0, 1] as $index) {
            $message = self::getMailerMessage($index);

            if ($message instanceof Email && 1 === preg_match('#(/adresse/[^"\s]+)#', (string) $message->getHtmlBody(), $found)) {
                return html_entity_decode($found[1]);
            }
        }

        self::fail('Aucun lien de confirmation dans les courriels envoyés.');
    }

    private function userOf(string $email): ?object
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);

        return $users->ofEmail(EmailAddress::fromString($email));
    }
}
