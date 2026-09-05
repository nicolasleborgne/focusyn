<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\OAuth;

use App\Identity\Domain\Model\OAuthProvider;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use RuntimeException;

/**
 * GitHub ne renvoie dans le profil que l'adresse *publique*, souvent absente,
 * et sans indication de vérification. L'adresse fiable s'obtient par un appel
 * séparé à /user/emails, qui liste les adresses avec leur statut.
 */
final readonly class GitHubProfileReader implements OAuthProfileReader
{
    private const string EMAILS_ENDPOINT = 'https://api.github.com/user/emails';

    public function supports(OAuthProvider $provider): bool
    {
        return OAuthProvider::GitHub === $provider;
    }

    public function read(OAuth2ClientInterface $client, AccessToken $token): OAuthProfile
    {
        $owner = $client->fetchUserFromToken($token);
        $provider = $client->getOAuth2Provider();

        $request = $provider->getAuthenticatedRequest('GET', self::EMAILS_ENDPOINT, $token);
        /** @var array<int, array{email?: string, primary?: bool, verified?: bool}> $emails */
        $emails = $provider->getParsedResponse($request);

        foreach ($emails as $candidate) {
            if (true === ($candidate['primary'] ?? false) && true === ($candidate['verified'] ?? false)) {
                $email = $candidate['email'] ?? '';

                if ('' !== $email) {
                    return new OAuthProfile((string) $owner->getId(), $email, emailVerified: true);
                }
            }
        }

        throw new RuntimeException('GitHub n\'expose aucune adresse principale vérifiée pour ce compte.');
    }
}
