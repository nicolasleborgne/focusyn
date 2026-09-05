<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\UserId;

/**
 * Ouvre une session pour un compte donné.
 *
 * Sans ce port, un contrôleur devrait fabriquer lui-même l'adaptateur de
 * sécurité — c'est-à-dire que la couche UI descendrait dans l'infrastructure.
 */
interface SessionStarter
{
    public function signIn(UserId $userId): void;
}
