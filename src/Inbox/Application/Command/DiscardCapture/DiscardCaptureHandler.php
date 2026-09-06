<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\DiscardCapture;

use App\Inbox\Application\Exception\CaptureNotFound;
use App\Inbox\Domain\Model\CaptureId;
use App\Inbox\Domain\Repository\CaptureRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Écarter, c'est supprimer. Sans corbeille : une boîte qu'on ne peut pas vider
 * cesse d'être une boîte, et ce qui mérite d'être gardé se classe en note.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class DiscardCaptureHandler
{
    public function __construct(
        private CaptureRepository $captures,
    ) {
    }

    public function __invoke(DiscardCapture $command): void
    {
        $capture = $this->captures->ofId(CaptureId::fromString($command->captureId))
            ?? throw CaptureNotFound::withId($command->captureId);

        $this->captures->remove($capture);
    }
}
