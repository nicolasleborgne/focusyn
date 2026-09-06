<?php

declare(strict_types=1);

namespace App\Privacy\UI\Http;

use App\Privacy\Application\Command\ErasePersonalData\ErasePersonalData;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Effacement, en deux temps.
 *
 * Le premier envoi arme, le second exécute. Une action irréversible derrière un
 * seul clic finit toujours par être déclenchée par accident.
 */
final class ErasePersonalDataController extends AbstractController
{
    private const string ARMED = 'privacy.erase_armed';

    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/donnees/effacer', 'en' => '/settings/data/erase'],
        name: 'privacy_erase',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('privacy-erase')]
    public function __invoke(Request $request): Response
    {
        $session = $request->getSession();

        if (true !== $session->get(self::ARMED)) {
            $session->set(self::ARMED, true);
            $this->addFlash('error', 'privacy.erase_confirm');

            return $this->redirectToRoute('settings');
        }

        $session->remove(self::ARMED);
        $this->commands->dispatch(new ErasePersonalData());
        $this->addFlash('success', 'privacy.erased');

        return $this->redirectToRoute('settings');
    }
}
