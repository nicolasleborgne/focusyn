<?php

declare(strict_types=1);

namespace App\Shared\Application\Command;

/**
 * Dispatch d'une intention vers son unique gestionnaire, et retour de son
 * résultat.
 *
 * Les contrôleurs passent par ce port plutôt que par `MessageBusInterface` :
 * la couche UI n'a pas à connaître Messenger, et le middleware transactionnel
 * reste un détail d'infrastructure.
 */
interface CommandBus
{
    public function dispatch(object $command): mixed;
}
