<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\OAuth;

/**
 * Ce que l'on retient d'un fournisseur : de quoi identifier la personne et
 * savoir si l'adresse peut être crue.
 */
final readonly class OAuthProfile
{
    public function __construct(
        public string $externalId,
        public string $email,
        public bool $emailVerified,
    ) {
    }
}
