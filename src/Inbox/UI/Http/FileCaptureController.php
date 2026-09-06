<?php

declare(strict_types=1);

namespace App\Inbox\UI\Http;

use App\Inbox\Application\Command\DiscardCapture\DiscardCapture;
use App\Inbox\Application\Command\FileAsNote\FileAsNote;
use App\Inbox\Application\Command\FileAsTask\FileAsTask;
use App\Inbox\Application\Exception\CaptureNotFound;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Trier une capture : en note, en tâche, ou dehors.
 *
 * Les trois voies partagent un contrôleur parce qu'elles partagent une rangée
 * de l'écran et le même jeton : ce sont trois boutons d'un seul formulaire, et
 * c'est le nom du bouton pressé qui décide.
 */
final class FileCaptureController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/boite/{id}/classer', 'en' => '/inbox/{id}/file'],
        name: 'inbox_file',
        requirements: ['id' => '[0-9a-f-]{36}'],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('inbox-file')]
    public function __invoke(Request $request, string $id): Response
    {
        try {
            if ($request->request->has('note')) {
                $noteId = $this->commands->dispatch(new FileAsNote($id));

                return $this->redirectToRoute('note_show', ['id' => $noteId]);
            }

            if ($request->request->has('task')) {
                $this->commands->dispatch(new FileAsTask($id, (string) $request->request->get('taskList', '')));
                $this->addFlash('success', 'inbox.filed_as_task');

                return $this->redirectToRoute('inbox');
            }

            $this->commands->dispatch(new DiscardCapture($id));
            $this->addFlash('success', 'inbox.discarded');
        } catch (CaptureNotFound) {
            // Deux clics sur le même bouton, ou la boîte triée depuis un autre
            // appareil : ce qui devait disparaître a disparu. Rien à signaler.
        }

        return $this->redirectToRoute('inbox');
    }
}
