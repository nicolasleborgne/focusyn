<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\RegenerateBackupCodes\RegenerateBackupCodes;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class RegenerateBackupCodesController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/codes-de-secours', 'en' => '/settings/backup-codes'],
        name: 'backup_codes_regenerate',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('backup-codes-regenerate')]
    public function __invoke(Request $request): Response
    {
        $userId = $this->account->idOrNull() ?? throw $this->createAccessDeniedException();

        /** @var list<string> $codes */
        $codes = $this->commands->dispatch(new RegenerateBackupCodes($userId));

        $request->getSession()->set(ConfirmTwoFactorEnrolmentController::FRESH_CODES_KEY, $codes);
        $this->addFlash('success', 'settings.backup_codes.regenerated');

        return $this->redirectToRoute('settings');
    }
}
