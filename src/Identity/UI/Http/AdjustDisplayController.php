<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\AdjustDisplay\AdjustDisplay;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use ValueError;

/**
 * Un seul point d'entrée pour tous les réglages d'affichage : chaque contrôle
 * de l'écran poste le sien, les autres restent inchangés.
 */
final class AdjustDisplayController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/affichage', 'en' => '/settings/display'],
        name: 'display_adjust',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('display-adjust')]
    public function __invoke(Request $request): Response
    {
        $userId = $this->account->idOrNull() ?? throw $this->createAccessDeniedException();

        try {
            $this->commands->dispatch(new AdjustDisplay(
                $userId,
                accent: self::stringOrNull($request, 'accent'),
                proseFont: self::stringOrNull($request, 'proseFont'),
                density: self::stringOrNull($request, 'density'),
                markOpacity: $request->request->has('markOpacity') ? (float) $request->request->get('markOpacity') : null,
                previewPane: $request->request->has('previewPane') ? $request->request->getBoolean('previewPane') : null,
            ));
        } catch (ValueError|InvalidArgumentException) {
            // Valeur hors des choix proposés : on l'ignore plutôt que de
            // présenter une erreur pour un réglage cosmétique.
        }

        return $this->redirectToRoute('settings');
    }

    private static function stringOrNull(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
