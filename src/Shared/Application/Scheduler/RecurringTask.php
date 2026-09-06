<?php

declare(strict_types=1);

namespace App\Shared\Application\Scheduler;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Une tâche que l'application rejoue à intervalle régulier.
 *
 * Les contextes déclarent la leur ; `RecurringSchedule` les agrège. Sans ce
 * port, le planificateur — qui vit dans Shared — devrait connaître les messages
 * de chaque contexte, ce qui inverserait la dépendance.
 */
#[AutoconfigureTag('app.recurring_task')]
interface RecurringTask
{
    /** Une fréquence lisible par le composant Scheduler : « 1 minute », « 1 day »… */
    public function frequency(): string;

    public function message(): object;
}
