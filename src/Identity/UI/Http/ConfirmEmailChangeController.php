<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\ConfirmEmailChange\ConfirmEmailChange;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Port\EmailChangeLink;
use App\Identity\Application\Port\SessionStarter;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Confirme la nouvelle adresse depuis le lien reçu.
 *
 * Adresse publique : le lien est cliqué depuis une boîte aux lettres, sans
 * garantie d'être connecté sur cet appareil. Ce qui protège est la signature du
 * lien et l'empreinte de la demande en cours.
 *
 * L'identifiant de connexion **est** l'adresse : la changer congédierait la
 * session en cours à la requête suivante. On la rouvre donc aussitôt, faute de
 * quoi confirmer son adresse déconnecterait.
 */
final class ConfirmEmailChangeController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly UserRepository $users,
        private readonly EmailChangeLink $links,
        private readonly SessionStarter $sessions,
    ) {
    }

    #[Route(
        path: '/adresse/{id}',
        name: 'email_change_confirm',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function __invoke(string $id, Request $request): Response
    {
        $userId = UserId::fromString($id);
        $user = $this->users->ofId($userId);

        // Lien expiré, demande annulée ou lien trafiqué : même réponse dans les
        // trois cas, pour ne rien apprendre à celui qui essaie.
        if (null === $user || !$this->links->isValidFor($user, $request->getUri())) {
            return $this->render(
                'identity/email_change.html.twig',
                ['expired' => true],
                new Response(status: Response::HTTP_GONE),
            );
        }

        try {
            $this->commands->dispatch(new ConfirmEmailChange($id));
        } catch (EmailAlreadyRegistered|InvalidArgumentException) {
            return $this->render(
                'identity/email_change.html.twig',
                ['expired' => true],
                new Response(status: Response::HTTP_GONE),
            );
        }

        $this->sessions->signIn($userId);
        $this->addFlash('success', 'email_change.done');

        return $this->redirectToRoute('settings');
    }
}
