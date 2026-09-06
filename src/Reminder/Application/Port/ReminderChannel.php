<?php

declare(strict_types=1);

namespace App\Reminder\Application\Port;

use App\Reminder\Application\Query\ReminderView;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un moyen de prévenir quelqu'un.
 *
 * Le courriel et la notification poussée en sont deux ; ils ne s'excluent pas.
 * Un canal muet — clés VAPID absentes, aucun appareil abonné — n'est pas une
 * erreur : il ne fait rien et le dit.
 */
#[AutoconfigureTag('app.reminder_channel')]
interface ReminderChannel
{
    /** @return bool vrai si le rappel est effectivement parti par ce canal */
    public function deliver(ReminderView $reminder, string $accountId, string $email, string $locale): bool;
}
