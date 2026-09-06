<?php

declare(strict_types=1);

namespace App\Inbox\UI\Http;

use App\Inbox\Application\Command\CaptureText\CaptureText;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class CaptureTextController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/boite/capturer', 'en' => '/inbox/capture'],
        name: 'inbox_capture',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('inbox-capture')]
    public function __invoke(Request $request): Response
    {
        try {
            $this->commands->dispatch(new CaptureText((string) $request->request->get('text', '')));
        } catch (InvalidArgumentException) {
            // Un champ vide envoyé par mégarde : on revient à la boîte sans
            // rien dire. Il n'y a pas d'erreur à réparer, seulement rien à
            // ranger.
        }

        return $this->redirectToRoute('inbox');
    }
}
