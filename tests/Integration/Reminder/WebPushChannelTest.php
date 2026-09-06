<?php

declare(strict_types=1);

namespace App\Tests\Integration\Reminder;

use App\Reminder\Application\Query\ReminderView;
use App\Reminder\Domain\Model\PushEndpoint;
use App\Reminder\Domain\Model\PushKeys;
use App\Reminder\Domain\Model\PushSubscription;
use App\Reminder\Domain\Model\PushSubscriptionId;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Repository\PushSubscriptionRepository;
use App\Reminder\Infrastructure\Push\WebPushChannel;
use DateTimeImmutable;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ce que le canal poussé fait *avant* de toucher au réseau.
 *
 * L'envoi lui-même n'est pas testé ici : il sortirait de la machine. Ce qui
 * compte et se vérifie, c'est qu'il renonce proprement — clés absentes, aucun
 * appareil, identifiant inconnu — plutôt que d'échouer et d'emporter le
 * courriel avec lui.
 */
final class WebPushChannelTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testWithoutVapidKeysTheChannelStaysSilent(): void
    {
        $channel = $this->channel(publicKey: '', privateKey: '');

        self::assertFalse($channel->isConfigured());
        self::assertFalse($channel->deliver($this->reminder(), RecipientId::generate()->toString(), 'nicolas@focusyn.fr', 'fr'));
    }

    public function testWithoutAnyDeviceNothingIsAttempted(): void
    {
        self::assertFalse(
            $this->channel()->deliver($this->reminder(), RecipientId::generate()->toString(), 'nicolas@focusyn.fr', 'fr'),
        );
    }

    public function testAnAccountIdentifierThatIsNotOneIsNotAnError(): void
    {
        // Le canal est appelé depuis un worker : y lever une exception
        // empêcherait le courriel de partir pour tous les rappels suivants.
        self::assertFalse($this->channel()->deliver($this->reminder(), 'pas-un-identifiant', 'nicolas@focusyn.fr', 'fr'));
    }

    public function testTheRegisteredDevicesOfSomeoneElseAreNeverUsed(): void
    {
        $mine = RecipientId::generate();
        $theirs = RecipientId::generate();
        $this->register($theirs);

        $subscriptions = self::getContainer()->get(PushSubscriptionRepository::class);
        self::assertInstanceOf(PushSubscriptionRepository::class, $subscriptions);

        self::assertCount(0, $subscriptions->ofSubscriber($mine));
        self::assertCount(1, $subscriptions->ofSubscriber($theirs));
    }

    private function register(RecipientId $owner): void
    {
        $subscriptions = self::getContainer()->get(PushSubscriptionRepository::class);
        self::assertInstanceOf(PushSubscriptionRepository::class, $subscriptions);

        $subscriptions->save(PushSubscription::register(
            PushSubscriptionId::generate(),
            $owner,
            PushEndpoint::fromString('https://fcm.googleapis.com/fcm/send/dQw4w9WgXcQ'),
            PushKeys::of(
                'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U',
                'k8JV6sjdbhAi92LxQjKUOg',
            ),
            new DateTimeImmutable('2026-09-05 12:00'),
        ));
    }

    private function channel(
        string $publicKey = 'BN_C3VNffjf2JEhmHUeUjO1EO-jIX-TxCfU7SC5ep7mYb2a1_JnukkcCKY3f5H7NMKKUavpFjUl10RoQNBH6QmY',
        string $privateKey = 'h1txCYwKPw01IG-2p7zQbkFVq5mIu-nQgslZ9U3RZcs',
    ): WebPushChannel {
        $subscriptions = self::getContainer()->get(PushSubscriptionRepository::class);
        self::assertInstanceOf(PushSubscriptionRepository::class, $subscriptions);

        return new WebPushChannel(
            $subscriptions,
            new NullLogger(),
            $publicKey,
            $privateKey,
            'mailto:bonjour@focusyn.fr',
        );
    }

    private function reminder(): ReminderView
    {
        return new ReminderView(
            id: '0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f',
            subject: 'note:0192c3f0-1f2b-7c3d-8e4f-5a6b7c8d9e0f',
            label: 'Relire la synthèse',
            dueAt: new DateTimeImmutable('2026-09-10 09:00'),
            notified: false,
        );
    }
}
