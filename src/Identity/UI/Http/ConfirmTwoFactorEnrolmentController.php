<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\EnableTwoFactor\EnableTwoFactor;
use App\Identity\Application\Exception\InvalidTotpCode;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ConfirmTwoFactorEnrolmentController extends AbstractController
{
    public const string FRESH_CODES_KEY = 'identity.two_factor.fresh_codes';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/double-authentification', 'en' => '/settings/two-factor'],
        name: 'two_factor_enrolment_confirm',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('two-factor-enrolment')]
    public function __invoke(Request $request): Response
    {
        $userId = $this->account->idOrNull() ?? throw $this->createAccessDeniedException();
        $session = $request->getSession();
        $pendingSecret = $session->get(ShowTwoFactorEnrolmentController::PENDING_SECRET_KEY);

        if (!\is_string($pendingSecret) || '' === $pendingSecret) {
            $this->addFlash('error', 'settings.two_factor.expired');

            return $this->redirectToRoute('two_factor_enrolment');
        }

        try {
            /** @var list<string> $codes */
            $codes = $this->commands->dispatch(new EnableTwoFactor(
                $userId,
                $pendingSecret,
                (string) $request->request->get('code', ''),
            ));
        } catch (InvalidTotpCode) {
            $this->addFlash('error', 'settings.two_factor.wrong_code');

            return $this->redirectToRoute('two_factor_enrolment');
        }

        $session->remove(ShowTwoFactorEnrolmentController::PENDING_SECRET_KEY);
        // Les codes ne sont montrés qu'une fois : ils transitent par la session
        // le temps d'une redirection, puis disparaissent.
        $session->set(self::FRESH_CODES_KEY, $codes);
        $this->addFlash('success', 'settings.two_factor.enabled');

        return $this->redirectToRoute('settings');
    }
}
