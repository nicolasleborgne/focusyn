<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Push;

use App\Reminder\Application\Port\ReminderChannel;
use App\Reminder\Application\Query\ReminderView;
use App\Reminder\Domain\Model\PushSubscription;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Repository\PushSubscriptionRepository;
use ErrorException;
use InvalidArgumentException;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Notification poussée, via le service de notification du navigateur.
 *
 * La charge est chiffrée avec les clés de l'appareil : le service la transporte
 * sans pouvoir la lire. C'est aussi pourquoi l'intitulé du rappel peut y
 * voyager.
 *
 * Sans clés VAPID configurées, le canal se tait — comme un fournisseur externe
 * non configuré ne présente pas de bouton. Une erreur à cet endroit empêcherait
 * le courriel de partir, alors que c'est justement lui le filet.
 */
final readonly class WebPushChannel implements ReminderChannel
{
    public function __construct(
        private PushSubscriptionRepository $subscriptions,
        private LoggerInterface $logger,
        private string $publicKey,
        private string $privateKey,
        private string $subject,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->publicKey && '' !== $this->privateKey;
    }

    public function deliver(ReminderView $reminder, string $accountId, string $email, string $locale): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $devices = $this->subscriptions->ofSubscriber(RecipientId::fromString($accountId));
        } catch (InvalidArgumentException) {
            return false;
        }

        if ([] === $devices) {
            return false;
        }

        $payload = json_encode([
            'title' => 'Focusyn',
            'body' => $reminder->label,
            'tag' => $reminder->id,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        return $this->push($devices, $payload);
    }

    /** @param list<PushSubscription> $devices */
    private function push(array $devices, string $payload): bool
    {
        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => $this->subject,
                'publicKey' => $this->publicKey,
                'privateKey' => $this->privateKey,
            ]]);
        } catch (ErrorException $error) {
            $this->logger->error('Clés VAPID inutilisables.', ['erreur' => $error->getMessage()]);

            return false;
        }

        $byEndpoint = [];

        foreach ($devices as $device) {
            $byEndpoint[$device->endpoint()->toString()] = $device;

            $webPush->queueNotification(
                new Subscription(
                    $device->endpoint()->toString(),
                    $device->keys()->publicKey(),
                    $device->keys()->authToken(),
                    'aes128gcm',
                ),
                $payload,
            );
        }

        $delivered = false;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered = true;

                continue;
            }

            // Un abonnement révoqué ne se répare pas : on le retire, faute de
            // quoi la table se remplirait d'appareils morts qu'on réessaierait
            // à chaque rappel.
            if ($report->isSubscriptionExpired() && isset($byEndpoint[$report->getEndpoint()])) {
                $this->subscriptions->remove($byEndpoint[$report->getEndpoint()]);

                continue;
            }

            $this->logger->warning('Notification poussée refusée.', ['raison' => $report->getReason()]);
        }

        return $delivered;
    }
}
