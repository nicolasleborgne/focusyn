<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\RewriteNote;

/**
 * Sauvegarde du corps depuis l'éditeur. Émise fréquemment, en différé : le
 * gestionnaire s'appuie sur l'agrégat pour ne rien écrire si rien n'a changé.
 */
final readonly class RewriteNote
{
    public function __construct(
        public string $noteId,
        public string $body,
    ) {
    }
}
