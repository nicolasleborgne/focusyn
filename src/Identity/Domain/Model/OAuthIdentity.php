<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use DateTimeImmutable;

/**
 * Compte externe rattaché à un compte Focusyn.
 *
 * Entité interne à l'agrégat User. Comme pour les appartenances d'organisation,
 * la référence arrière est imposée par l'ORM et reste privée.
 */
final class OAuthIdentity
{
    public function __construct(
        private readonly User $user,
        private readonly OAuthIdentityId $id,
        private readonly OAuthProvider $provider,
        private readonly string $externalId,
        private readonly DateTimeImmutable $linkedAt,
    ) {
    }

    public function user(): User
    {
        return $this->user;
    }

    public function id(): OAuthIdentityId
    {
        return $this->id;
    }

    public function provider(): OAuthProvider
    {
        return $this->provider;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function linkedAt(): DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
