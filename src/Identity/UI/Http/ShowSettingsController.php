<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Query\AccountSettingsQuery;
use App\Identity\Domain\Model\OAuthProvider;
use App\Identity\Domain\Model\UserId;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowSettingsController extends AbstractController
{
    public function __construct(
        private readonly AccountSettingsQuery $settings,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages', 'en' => '/settings'],
        name: 'settings',
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        $userId = $this->account->idOrNull() ?? throw $this->createAccessDeniedException();

        return $this->render('identity/settings.html.twig', [
            'section' => ShellSection::Settings,
            'account' => $this->settings->forUser(UserId::fromString($userId)),
            'providers' => OAuthProvider::cases(),
            // Codes de secours fraîchement générés, transmis une seule fois par
            // la session : ils ne sont plus lisibles ensuite.
            'freshBackupCodes' => $this->consumeFreshCodes(),
        ]);
    }

    /** @return list<string> */
    private function consumeFreshCodes(): array
    {
        $session = $this->container->get('request_stack')->getSession();
        /** @var list<string> $codes */
        $codes = $session->get(ConfirmTwoFactorEnrolmentController::FRESH_CODES_KEY, []);
        $session->remove(ConfirmTwoFactorEnrolmentController::FRESH_CODES_KEY);

        return $codes;
    }
}
