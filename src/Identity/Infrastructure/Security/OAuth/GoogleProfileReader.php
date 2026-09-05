<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\OAuth;

use App\Identity\Domain\Model\OAuthProvider;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use RuntimeException;

final readonly class GoogleProfileReader implements OAuthProfileReader
{
    public function supports(OAuthProvider $provider): bool
    {
        return OAuthProvider::Google === $provider;
    }

    public function read(OAuth2ClientInterface $client, AccessToken $token): OAuthProfile
    {
        $owner = $client->fetchUserFromToken($token);
        $raw = $owner->toArray();

        $email = $raw['email'] ?? null;

        if (!\is_string($email) || '' === $email) {
            throw new RuntimeException('Google n\'a pas communiqué d\'adresse électronique.');
        }

        return new OAuthProfile(
            externalId: (string) $owner->getId(),
            email: $email,
            // Google renseigne explicitement la vérification ; en son absence,
            // on considère l'adresse comme non vérifiée plutôt que l'inverse.
            emailVerified: true === ($raw['email_verified'] ?? false),
        );
    }
}
