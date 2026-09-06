<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Inbox;

use App\Notebook\Application\Command\WriteNote\WriteNote;
use App\Notebook\Application\Command\WriteNote\WriteNoteHandler;
use App\Shared\Application\Notebook\NoteWriter;

/**
 * Le gestionnaire est appelé directement, sans repasser par le bus : on est
 * déjà dans la transaction du cas d'usage qui trie la capture, et il faut que
 * la note écrite et la capture retirée tiennent ou tombent ensemble. Un second
 * envoi sur le bus ouvrirait une transaction dans la transaction.
 */
final readonly class HandlerNoteWriter implements NoteWriter
{
    public function __construct(
        private WriteNoteHandler $writeNote,
    ) {
    }

    public function write(string $title, string $body): string
    {
        return ($this->writeNote)(new WriteNote($title, $body))->toString();
    }
}
