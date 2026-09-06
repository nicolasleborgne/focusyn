<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model;

/**
 * Les fournisseurs que l'on sait appeler.
 *
 * Les modèles sont énumérés ici plutôt que devinés à l'exécution : découvrir
 * les modèles disponibles demanderait un appel réseau — donc une clé valide —
 * avant même que l'écran des réglages puisse s'afficher.
 */
enum Provider: string
{
    case Anthropic = 'anthropic';
    case OpenAI = 'openai';
    case Google = 'google';
    case Ollama = 'ollama';

    /** @return list<string> */
    public function models(): array
    {
        return match ($this) {
            self::Anthropic => ['claude-sonnet-4-5', 'claude-haiku-4-5'],
            self::OpenAI => ['gpt-5', 'gpt-5-mini'],
            self::Google => ['gemini-2.5-pro', 'gemini-2.5-flash'],
            self::Ollama => ['llama3.1', 'mistral'],
        };
    }

    public function defaultModel(): string
    {
        return $this->models()[0];
    }

    /** Tourne sur la machine de la personne : pas de clé, mais une adresse. */
    public function isLocal(): bool
    {
        return self::Ollama === $this;
    }
}
