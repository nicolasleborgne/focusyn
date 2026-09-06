<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

/**
 * Ferme une session pour de bon.
 *
 * Retirer une ligne de la liste ne suffirait pas : le navigateur d'en face
 * garderait son cookie et continuerait d'entrer. Cet adaptateur détruit la
 * session elle-même, là où elle est stockée.
 */
interface SessionCloser
{
    public function close(string $sessionId): void;
}
