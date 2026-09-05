<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\Command\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerCommandBus implements CommandBus
{
    use HandleTrait;

    public function __construct(
        #[Autowire(service: 'command.bus')]
        MessageBusInterface $commandBus,
    ) {
        $this->messageBus = $commandBus;
    }

    public function dispatch(object $command): mixed
    {
        try {
            return $this->handle($command);
        } catch (HandlerFailedException $exception) {
            // Messenger enveloppe toute exception levée par un gestionnaire.
            // La déballer ici est la raison d'être de ce port : un contrôleur
            // attrape `EmailAlreadyRegistered`, pas une exception de transport.
            throw $exception->getPrevious() ?? $exception;
        }
    }
}
