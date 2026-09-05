<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Command\RewriteNote\RewriteNote;
use App\Notebook\Application\Exception\NoteNotFound;
use App\Notebook\Application\Query\MarkdownOutline;
use App\Notebook\Domain\Model\NoteBody;
use App\Shared\Application\Command\CommandBus;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Sauvegarde différée du corps, appelée par l'éditeur.
 *
 * Le jeton CSRF passe par un en-tête plutôt que par le corps : la requête est
 * du JSON, pas un formulaire.
 */
final class SaveNoteBodyController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly MarkdownOutline $outline,
    ) {
    }

    #[Route(
        path: ['fr' => '/notes/{id}/corps', 'en' => '/notes/{id}/body'],
        name: 'note_save_body',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        // Le jeton est validé ici plutôt que par l'attribut : celui-ci lit un
        // paramètre de formulaire, or cette requête est du JSON et porte son
        // jeton dans un en-tête.
        if (!$this->csrf->isTokenValid(new CsrfToken('note-save', (string) $request->headers->get('X-CSRF-Token')))) {
            return new JsonResponse(['error' => 'invalid_csrf_token'], Response::HTTP_FORBIDDEN);
        }

        /** @var array{body?: mixed} $payload */
        $payload = json_decode((string) $request->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $body = $payload['body'] ?? null;

        if (!\is_string($body)) {
            return new JsonResponse(['error' => 'body_expected'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $updatedAt = $this->commands->dispatch(new RewriteNote($id, $body));
        } catch (NoteNotFound) {
            throw $this->createNotFoundException();
        }

        return new JsonResponse([
            'savedAt' => $updatedAt instanceof DateTimeImmutable ? $updatedAt->format(\DATE_ATOM) : null,
            // L'aperçu est rendu ici plutôt que reconstruit côté client : un
            // second analyseur markdown finirait par diverger du premier.
            'preview' => $this->renderView('notebook/_prose.html.twig', [
                'lines' => $this->outline->lines(NoteBody::fromString($body), withMarks: false),
                'marks' => false,
            ]),
        ]);
    }
}
