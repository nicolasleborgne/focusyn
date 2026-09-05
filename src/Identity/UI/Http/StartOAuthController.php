<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Domain\Model\OAuthProvider;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

final class StartOAuthController extends AbstractController
{
    private const array SCOPES = [
        'google' => ['openid', 'email', 'profile'],
        // `user:email` donne accès à /user/emails, seul endroit où GitHub
        // indique quelle adresse est principale et vérifiée.
        'github' => ['read:user', 'user:email'],
    ];

    public function __construct(
        private readonly ClientRegistry $clients,
    ) {
    }

    #[Route(
        path: ['fr' => '/connexion/{provider}', 'en' => '/login/{provider}'],
        name: 'oauth_start',
        requirements: ['provider' => new EnumRequirement(OAuthProvider::class)],
        methods: ['GET'],
    )]
    public function __invoke(string $provider): Response
    {
        return $this->clients->getClient($provider)->redirect(self::SCOPES[$provider], []);
    }
}
