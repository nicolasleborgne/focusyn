<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Scheduler;

use App\Reminder\Application\Command\NotifyDueReminders\NotifyDueReminders;
use App\Shared\Application\Scheduler\RecurringTask;

/**
 * Le tour de garde des rappels.
 *
 * Toutes les minutes : c'est la plus petite unité que propose le dialogue, et
 * un rappel qui arrive avec une minute de retard reste un rappel à l'heure.
 */
final readonly class WatchDueReminders implements RecurringTask
{
    public function frequency(): string
    {
        return '1 minute';
    }

    public function message(): object
    {
        return new NotifyDueReminders();
    }
}
