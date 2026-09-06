<?php

declare(strict_types=1);

namespace App\Tests\Functional\Reminder;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Reminder\Domain\Model\PushSubscription;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Repository\PushSubscriptionRepository;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * L'abonnement d'un navigateur aux notifications poussées.
 *
 * Le point de réception fait l'identité de l'appareil : ces tests vérifient
 * surtout qu'un même navigateur ne s'inscrit jamais deux fois, et qu'on ne peut
 * pas désabonner l'appareil de quelqu'un d'autre.
 */
final class PushSubscriptionTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    private const string ENDPOINT = 'https://fcm.googleapis.com/fcm/send/dQw4w9WgXcQ';
    private const string P256DH = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';

    public function testTheSettingsOfferTheSwitchWhenTheServerHasItsKeys(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $row = $client->request('GET', '/reglages')->filter('[data-controller="push"]');

        self::assertCount(1, $row);
        self::assertNotSame('', (string) $row->attr('data-push-public-key-value'));
    }

    public function testRegisteringADeviceKeepsIt(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);

        $this->subscribe($client);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->devicesOf($account->getUserIdentifier(), $client));
    }

    public function testTheSameBrowserNeverRegistersTwice(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);

        $this->subscribe($client);
        // Le navigateur a renouvelé ses clés sans changer d'adresse.
        $this->subscribe($client, auth: 'zzJV6sjdbhAi92LxQjKUOg');

        $devices = $this->devicesOf($account->getUserIdentifier(), $client);

        self::assertCount(1, $devices);
        self::assertSame('zzJV6sjdbhAi92LxQjKUOg', $devices[0]->keys()->authToken());
    }

    public function testUnsubscribingForgetsTheDevice(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->subscribe($client);

        $this->call($client, 'DELETE', ['endpoint' => self::ENDPOINT]);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->devicesOf($account->getUserIdentifier(), $client));
    }

    public function testOneAccountCannotUnsubscribeAnotherAccountDevice(): void
    {
        $client = self::createClient();
        $other = $this->logIn($client, 'autre@focusyn.fr');
        $this->subscribe($client);
        $this->signOut($client);

        $this->logIn($client);
        $this->call($client, 'DELETE', ['endpoint' => self::ENDPOINT]);

        // La requête aboutit — rien ne prouve à l'appelant que l'appareil
        // existe — mais l'abonnement de l'autre est toujours là.
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->devicesOf($other->getUserIdentifier(), $client));
    }

    public function testAnEndpointThatIsNotHttpsIsRefused(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->call($client, 'POST', [
            'endpoint' => 'http://exemple.fr/push',
            'keys' => ['p256dh' => self::P256DH, 'auth' => 'k8JV6sjdbhAi92LxQjKUOg'],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testAMissingEndpointIsRefused(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $this->call($client, 'POST', ['keys' => ['p256dh' => self::P256DH, 'auth' => 'k8JV6sjdbhAi92LxQjKUOg']]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testWithoutTheHeaderTokenNothingIsRegistered(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);

        $client->request(
            'POST',
            '/rappels/abonnement',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'endpoint' => self::ENDPOINT,
                'keys' => ['p256dh' => self::P256DH, 'auth' => 'k8JV6sjdbhAi92LxQjKUOg'],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->devicesOf($account->getUserIdentifier(), $client));
    }

    private function subscribe(KernelBrowser $client, string $auth = 'k8JV6sjdbhAi92LxQjKUOg'): void
    {
        $this->call($client, 'POST', [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => self::P256DH, 'auth' => $auth],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function call(KernelBrowser $client, string $method, array $payload): void
    {
        $token = (string) $client->request('GET', '/reglages')
            ->filter('[data-controller="push"]')
            ->attr('data-push-token-value');

        $client->request(
            $method,
            '/rappels/abonnement',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    /** @return list<PushSubscription> */
    private function devicesOf(string $email, KernelBrowser $client): array
    {
        $subscriptions = self::getContainer()->get(PushSubscriptionRepository::class);
        self::assertInstanceOf(PushSubscriptionRepository::class, $subscriptions);

        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString($email));
        self::assertNotNull($user);

        return $subscriptions->ofSubscriber(RecipientId::fromString($user->id()->toString()));
    }
}
