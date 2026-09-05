<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\OAuth;

use App\Identity\Application\Port\AvailableOAuthProviders;
use App\Identity\Domain\Model\OAuthProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ConfiguredOAuthProviders implements AvailableOAuthProviders
{
    public function __construct(
        #[Autowire('%env(OAUTH_GOOGLE_ID)%')]
        private string $googleClientId,
        #[Autowire('%env(OAUTH_GITHUB_ID)%')]
        private string $githubClientId,
    ) {
    }

    public function all(): array
    {
        return array_values(array_filter(
            OAuthProvider::cases(),
            fn (OAuthProvider $provider): bool => '' !== trim(match ($provider) {
                OAuthProvider::Google => $this->googleClientId,
                OAuthProvider::GitHub => $this->githubClientId,
            }),
        ));
    }
}
