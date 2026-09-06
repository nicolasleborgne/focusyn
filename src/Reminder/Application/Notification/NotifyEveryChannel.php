<?php

declare(strict_types=1);

namespace App\Reminder\Application\Notification;

use App\Reminder\Application\Port\ReminderChannel;
use App\Reminder\Application\Port\ReminderNotifier;
use App\Reminder\Application\Query\ReminderView;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Prévient par tous les canaux disponibles.
 *
 * Tous, et non « le meilleur » : une notification poussée arrive sur l'appareil
 * allumé, un courriel attend qu'on l'ouvre. Les deux sont utiles, et un rappel
 * manqué coûte plus cher qu'un rappel reçu deux fois.
 */
final readonly class NotifyEveryChannel implements ReminderNotifier
{
    /** @param iterable<ReminderChannel> $channels */
    public function __construct(
        #[AutowireIterator('app.reminder_channel')]
        private iterable $channels,
    ) {
    }

    public function notify(ReminderView $reminder, string $accountId, string $email, string $locale): void
    {
        foreach ($this->channels as $channel) {
            $channel->deliver($reminder, $accountId, $email, $locale);
        }
    }
}
