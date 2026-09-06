<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Domain\Model\Device;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\LoginSession;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\LoginSessionRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Tests\Functional\LogsIn;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Les sessions ouvertes, telles que l'écran des réglages les présente.
 *
 * Ce qui compte ici : qu'on ne puisse fermer que les siennes, et que fermer
 * la sienne soit une déconnexion plutôt qu'un tour de passe-passe.
 */
final class SessionsTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testTheCurrentSessionIsListedAndSaysSo(): void
    {
        $client = self::createClient();
        $this->signIn($client);

        $crawler = $client->request('GET', '/reglages');

        self::assertStringContainsString('session en cours', $crawler->filter('.fx-settings__section')->first()->text());
    }

    public function testConnectingRecordsTheDeviceThatConnected(): void
    {
        $client = self::createClient(server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0']);
        $this->signIn($client);

        $sessions = $this->sessionsOf('nicolas@focusyn.fr');

        self::assertCount(1, $sessions);
        self::assertSame('Firefox — Linux', $sessions[0]->device()->toString());
    }

    public function testAnotherAccountSessionCannotBeClosed(): void
    {
        $client = self::createClient();
        $this->signIn($client, 'autre@focusyn.fr');
        $theirs = $this->sessionsOf('autre@focusyn.fr');
        self::assertCount(1, $theirs);
        $client->request('POST', '/deconnexion');

        $this->signIn($client);
        $token = (string) $client->request('GET', '/reglages')
            ->filter('form[action="/reglages/sessions/fermer"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/reglages/sessions/fermer', [
            'sessionId' => $theirs[0]->id(),
            '_token' => $token,
        ]);

        // La requête aboutit — rien ne prouve à l'appelant que la session
        // existe — mais celle de l'autre est toujours là.
        self::assertCount(1, $this->sessionsOf('autre@focusyn.fr'));
    }

    public function testClosingOnesOwnSessionIsALogout(): void
    {
        $client = self::createClient();
        $this->signIn($client);

        $crawler = $client->request('GET', '/reglages');
        $client->submit($crawler->filter('form[action="/reglages/sessions/fermer"]')->form());

        self::assertResponseRedirects('/deconnexion');
    }

    private function signIn(KernelBrowser $client, string $email = 'nicolas@focusyn.fr'): void
    {
        $this->logIn($client, $email);
        // `loginUser()` n'émet pas l'événement de connexion : on passe par un
        // écran pour que la session existe, puis on inscrit la session à la
        // main, comme le ferait le pare-feu.
        $client->request('GET', '/');
        $this->recordSessionOf($client, $email);
    }

    private function recordSessionOf(KernelBrowser $client, string $email): void
    {
        $sessions = self::getContainer()->get(LoginSessionRepository::class);
        self::assertInstanceOf(LoginSessionRepository::class, $sessions);

        $id = $client->getRequest()->getSession()->getId();

        if (null === $sessions->ofId($id)) {
            $sessions->save(LoginSession::open(
                $id,
                UserId::fromString($this->userIdOf($email)),
                Device::fromUserAgent((string) $client->getRequest()->headers->get('User-Agent')),
                new DateTimeImmutable(),
            ));
        }
    }

    /** @return list<LoginSession> */
    private function sessionsOf(string $email): array
    {
        $sessions = self::getContainer()->get(LoginSessionRepository::class);
        self::assertInstanceOf(LoginSessionRepository::class, $sessions);

        return $sessions->ofUser(UserId::fromString($this->userIdOf($email)));
    }

    private function userIdOf(string $email): string
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString($email));
        self::assertNotNull($user);

        return $user->id()->toString();
    }
}
