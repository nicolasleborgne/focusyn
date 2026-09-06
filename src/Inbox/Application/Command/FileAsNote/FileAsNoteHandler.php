<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\FileAsNote;

use App\Inbox\Application\Exception\CaptureNotFound;
use App\Inbox\Domain\Model\CaptureId;
use App\Inbox\Domain\Repository\CaptureRepository;
use App\Shared\Application\Notebook\NoteWriter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * La capture devient une note, et cesse d'être une capture.
 *
 * L'écriture vient d'abord : si le plafond de notes l'arrête, la transaction
 * tombe et la capture reste dans la boîte. La perdre pour une note qui n'a pas
 * pu s'écrire serait la pire issue possible — c'est justement ce que la boîte
 * doit empêcher.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class FileAsNoteHandler
{
    public function __construct(
        private CaptureRepository $captures,
        private NoteWriter $notes,
    ) {
    }

    public function __invoke(FileAsNote $command): string
    {
        $capture = $this->captures->ofId(CaptureId::fromString($command->captureId))
            ?? throw CaptureNotFound::withId($command->captureId);

        $noteId = $this->notes->write($capture->title()->toString(), $capture->body());

        $this->captures->remove($capture);

        return $noteId;
    }
}
