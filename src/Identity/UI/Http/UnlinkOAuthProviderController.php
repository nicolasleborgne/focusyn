<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\UnlinkOAuthProvider\UnlinkOAuthProvider;
use App\Identity\Domain\Model\OAuthProvider;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class UnlinkOAuthProviderController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/fournisseurs/{provider}/detacher', 'en' => '/settings/providers/{provider}/unlink'],
        name: 'oauth_unlink',
        requirements: ['provider' => new EnumRequirement(OAuthProvider::class)],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('oauth-unlink')]
    public function __invoke(string $provider): Response
    {
        $userId = $this->account->idOrNull() ?? throw $this->createAccessDeniedException();

        $this->commands->dispatch(new UnlinkOAuthProvider($userId, $provider));
        $this->addFlash('success', 'settings.providers.unlinked');

        return $this->redirectToRoute('settings');
    }
}
