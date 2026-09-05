<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\DisableTwoFactor\DisableTwoFactor;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class DisableTwoFactorController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/double-authentification/desactiver', 'en' => '/settings/two-factor/disable'],
        name: 'two_factor_disable',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('two-factor-disable')]
    public function __invoke(): Response
    {
        $userId = $this->account->idOrNull() ?? throw $this->createAccessDeniedException();

        $this->commands->dispatch(new DisableTwoFactor($userId));
        $this->addFlash('success', 'settings.two_factor.disabled');

        return $this->redirectToRoute('settings');
    }
}
