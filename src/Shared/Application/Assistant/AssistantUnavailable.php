<?php

declare(strict_types=1);

namespace App\Shared\Application\Assistant;

use RuntimeException;

/**
 * L'assistant ne peut pas répondre, et le message est une clé de traduction :
 * l'écran dit quoi, sans que l'appelant ait à connaître les raisons possibles.
 */
final class AssistantUnavailable extends RuntimeException
{
    public static function because(string $translationKey): self
    {
        return new self($translationKey);
    }
}
