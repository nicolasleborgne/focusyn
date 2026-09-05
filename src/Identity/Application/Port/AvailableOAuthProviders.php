<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\OAuthProvider;

/**
 * Fournisseurs réellement utilisables sur cette instance.
 *
 * Un bouton « Continuer avec Google » qui mène à une erreur de configuration
 * est pire que pas de bouton du tout : l'écran ne montre que ce qui marche.
 */
interface AvailableOAuthProviders
{
    /** @return list<OAuthProvider> */
    public function all(): array;
}
