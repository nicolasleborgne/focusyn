<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command\AskAssistant;

use App\Assistant\Application\Completion;
use App\Assistant\Application\Exception\AssistantRefused;
use App\Shared\Application\Notebook\NoteSource;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Interroge le modèle sur une note.
 *
 * Les vérifications — consentement, palier, réglages, clé — vivent dans
 * `Completion`, qui les tient dans le bon ordre pour tous les appelants. Ce
 * gestionnaire ne décide plus que d'une chose : **ce qui part**.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AskAssistantHandler
{
    public function __construct(
        private Completion $completion,
        private NoteSource $notes,
    ) {
    }

    public function __invoke(AskAssistant $command): string
    {
        // La source est passée close, non lue : `Completion` ne l'ouvrira
        // qu'une fois le consentement et le palier vérifiés.
        return $this->completion->of($command->instruction, fn (): string => $this->sourceFor($command));
    }

    /**
     * Ce qui part réellement au modèle.
     *
     * Le réglage « envoyer la note entière » est appliqué **ici**, côté serveur :
     * le laisser au client reviendrait à ce qu'une page mal à jour envoie toute
     * une note qu'on avait justement demandé de ne pas envoyer.
     */
    private function sourceFor(AskAssistant $command): string
    {
        if (!$this->completion->sendsWholeNote()) {
            $selection = trim($command->selection);

            return '' === $selection
                ? throw new AssistantRefused('assistant.error.no_selection') : $selection;
        }

        return $this->notes->textOf($command->noteId)
            ?? throw new AssistantRefused('assistant.error.note_gone');
    }
}
