<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reminder\Domain;

use App\Reminder\Domain\Model\PushEndpoint;
use App\Reminder\Domain\Model\PushKeys;
use App\Reminder\Domain\Model\PushSubscription;
use App\Reminder\Domain\Model\PushSubscriptionId;
use App\Reminder\Domain\Model\RecipientId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushSubscription::class)]
final class PushSubscriptionTest extends TestCase
{
    private const string ENDPOINT = 'https://fcm.googleapis.com/fcm/send/dQw4w9WgXcQ';

    public function testAnEndpointMustBeAnHttpsAddress(): void
    {
        self::assertSame(self::ENDPOINT, PushEndpoint::fromString(self::ENDPOINT)->toString());

        // Sans TLS, la charge chiffrée voyagerait en clair jusqu'au service de
        // notification : aucun navigateur ne propose cela, mais rien n'oblige
        // un client à être un navigateur.
        $this->expectExceptionMessage('Un point de réception doit être une adresse https.');
        PushEndpoint::fromString('http://exemple.fr/push');
    }

    public function testAnEmptyEndpointIsRefused(): void
    {
        $this->expectExceptionMessage('Un point de réception doit être une adresse https.');
        PushEndpoint::fromString('');
    }

    public function testKeysMustBothBePresent(): void
    {
        $keys = PushKeys::of('BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U', 'k8JV6sjdbhAi92LxQjKUOg');

        self::assertStringStartsWith('BEl62', $keys->publicKey());
        self::assertSame('k8JV6sjdbhAi92LxQjKUOg', $keys->authToken());

        $this->expectExceptionMessage('Un abonnement doit porter ses deux clés.');
        PushKeys::of('BEl62iUYgUivxIkv69yViEuiBIa', '');
    }

    public function testAKeyThatIsNotBaseSixtyFourUrlIsRefused(): void
    {
        $this->expectExceptionMessage('Un abonnement doit porter ses deux clés.');
        PushKeys::of('clé avec des espaces', 'k8JV6sjdbhAi92LxQjKUOg');
    }

    public function testRegisteringRemembersTheDeviceAndItsOwner(): void
    {
        $owner = RecipientId::generate();
        $subscription = $this->register($owner);

        self::assertTrue($subscription->subscriberId()->equals($owner));
        self::assertSame(self::ENDPOINT, $subscription->endpoint()->toString());
        self::assertSame('2026-09-05 12:00', $subscription->lastSeenAt()->format('Y-m-d H:i'));
    }

    public function testSeeingAKnownDeviceAgainRefreshesItRatherThanDuplicatingIt(): void
    {
        $subscription = $this->register(RecipientId::generate());

        // Un navigateur renouvelle ses clés sans changer d'adresse : le même
        // appareil doit être mis à jour, pas ajouté une seconde fois.
        $renewed = PushKeys::of('BNewKeyForTheSameDevice-0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVW', 'zzJV6sjdbhAi92LxQjKUOg');
        $subscription->seenAgain($renewed, new DateTimeImmutable('2026-09-20 08:00'));

        self::assertSame('zzJV6sjdbhAi92LxQjKUOg', $subscription->keys()->authToken());
        self::assertSame('2026-09-20 08:00', $subscription->lastSeenAt()->format('Y-m-d H:i'));
    }

    private function register(RecipientId $owner): PushSubscription
    {
        return PushSubscription::register(
            PushSubscriptionId::generate(),
            $owner,
            PushEndpoint::fromString(self::ENDPOINT),
            PushKeys::of('BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U', 'k8JV6sjdbhAi92LxQjKUOg'),
            new DateTimeImmutable('2026-09-05 12:00'),
        );
    }
}
