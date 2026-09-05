<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\ResetPassword\ResetPassword;
use App\Identity\Application\Port\PasswordResetLink;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ResetPasswordController extends AbstractController
{
    private const int MINIMUM_LENGTH = 12;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly UserRepository $users,
        private readonly PasswordResetLink $links,
    ) {
    }

    #[Route(
        path: '/mot-de-passe/{id}',
        name: 'password_reset',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET', 'POST'],
    )]
    public function __invoke(string $id, Request $request): Response
    {
        $user = $this->users->ofId(UserId::fromString($id));

        // Lien expiré, déjà utilisé ou trafiqué : même réponse dans les trois
        // cas, pour ne rien apprendre à celui qui essaie.
        if (null === $user || !$this->links->isValidFor($user, $request->getUri())) {
            return $this->render('identity/password_reset.html.twig', ['expired' => true], new Response(status: Response::HTTP_GONE));
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $plainPassword = $request->request->getString('password');

            if (mb_strlen($plainPassword) < self::MINIMUM_LENGTH) {
                $error = 'registration.password.too_short';
            } else {
                $this->commands->dispatch(new ResetPassword($id, $plainPassword));
                $this->addFlash('success', 'password_reset.done');

                return $this->redirectToRoute('login');
            }
        }

        return $this->render('identity/password_reset.html.twig', [
            'expired' => false,
            'error' => $error,
        ]);
    }
}
