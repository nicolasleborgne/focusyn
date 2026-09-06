<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\NotifyDueReminders;

/**
 * « Regarde s'il y a des rappels à envoyer. ».
 *
 * Sans paramètre : le moment est celui de l'exécution, et le message est
 * déposé par le planificateur, pas par un humain.
 */
final readonly class NotifyDueReminders
{
}
