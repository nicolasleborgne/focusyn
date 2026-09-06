<?php

declare(strict_types=1);

namespace App\Privacy\UI\Http;

use App\Privacy\Application\Command\AdjustPrivacy\AdjustPrivacy;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use ValueError;

final class AdjustPrivacyController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/donnees', 'en' => '/settings/data'],
        name: 'privacy_adjust',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('privacy-adjust')]
    public function __invoke(Request $request): Response
    {
        $subjectId = $this->account->idOrNull() ?? throw $this->createAccessDeniedException();

        try {
            $this->commands->dispatch(new AdjustPrivacy(
                $subjectId,
                consent: null !== $request->request->get('consent') ? $request->request->getString('consent') : null,
                granted: $request->request->has('granted') ? $request->request->getBoolean('granted') : null,
                retention: null !== $request->request->get('retention') ? $request->request->getString('retention') : null,
            ));
            $this->addFlash('success', 'privacy.saved');
        } catch (ValueError) {
            // Choix hors des valeurs proposées : ignoré.
        }

        return $this->redirectToRoute('settings');
    }
}
