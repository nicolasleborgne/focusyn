<?php

declare(strict_types=1);

namespace App\Reminder\UI\TwigComponent;

use App\Reminder\Application\Query\ReminderChips;
use App\Reminder\Application\Query\ReminderView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * La puce d'échéance posée à côté d'une note ou d'une tâche.
 *
 * Portée par Reminder et appelée par les gabarits de Notebook et de Task : ces
 * contextes affichent le rappel sans avoir le droit de le connaître.
 */
#[AsTwigComponent(name: 'ReminderChip', template: 'components/ReminderChip.html.twig')]
final class ReminderChip
{
    public string $subject = '';
    public string $label = '';

    public function __construct(
        private readonly ReminderChips $chips,
    ) {
    }

    public function reminder(): ?ReminderView
    {
        return $this->chips->of($this->subject);
    }
}
