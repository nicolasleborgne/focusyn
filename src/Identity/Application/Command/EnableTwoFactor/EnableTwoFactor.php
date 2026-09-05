<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\EnableTwoFactor;

use SensitiveParameter;

/**
 * Confirme l'enrôlement d'une application d'authentification.
 *
 * Le secret transite ici depuis la session : il n'est écrit en base qu'après
 * qu'un premier code valide a prouvé que l'utilisateur l'a bien enregistré.
 * L'écrire plus tôt exposerait au compte verrouillé par un QR jamais scanné.
 */
final readonly class EnableTwoFactor
{
    public function __construct(
        public string $userId,
        #[SensitiveParameter]
        public string $pendingSecret,
        #[SensitiveParameter]
        public string $code,
    ) {
    }
}
