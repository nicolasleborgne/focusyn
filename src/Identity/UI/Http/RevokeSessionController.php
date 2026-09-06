<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\RevokeSession\RevokeSession;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class RevokeSessionController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/sessions/fermer', 'en' => '/settings/sessions/close'],
        name: 'session_revoke',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('session-revoke')]
    public function __invoke(Request $request): Response
    {
        $sessionId = $request->request->getString('sessionId');

        // Fermer la sienne, c'est se déconnecter : le pare-feu s'en charge
        // mieux que nous, et le faire ici laisserait la requête sans session.
        if ($sessionId === $request->getSession()->getId()) {
            return $this->redirectToRoute('logout');
        }

        $this->commands->dispatch(new RevokeSession($sessionId));
        $this->addFlash('success', 'settings.sessions.revoked');

        return $this->redirectToRoute('settings');
    }
}
