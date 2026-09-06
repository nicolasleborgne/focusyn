<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Notebook;

use App\Assistant\Application\Completion;
use App\Assistant\Application\Exception\AssistantRefused;
use App\Shared\Application\Assistant\AssistantUnavailable;
use App\Shared\Application\Assistant\WritingAssistant;
use Closure;

/**
 * L'assistant tel que les autres contextes peuvent s'en servir.
 *
 * Le refus change de type en passant la frontière : `AssistantRefused` est
 * interne, et un contexte qui l'attraperait dépendrait des exceptions
 * d'Assistant. La clé de traduction, elle, traverse — c'est tout ce dont
 * l'appelant a besoin pour l'afficher.
 */
final readonly class PortableWritingAssistant implements WritingAssistant
{
    public function __construct(
        private Completion $completion,
    ) {
    }

    public function complete(string $instruction, Closure $source): string
    {
        try {
            return $this->completion->of($instruction, $source);
        } catch (AssistantRefused $refusal) {
            throw AssistantUnavailable::because($refusal->getMessage());
        }
    }
}
