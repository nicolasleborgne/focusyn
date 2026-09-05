<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\RequestPasswordReset\RequestPasswordReset;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RequestPasswordResetController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/mot-de-passe-oublie', 'en' => '/forgotten-password'],
        name: 'password_request',
        methods: ['GET', 'POST'],
    )]
    public function __invoke(Request $request): Response
    {
        $sent = false;
        $email = $request->request->getString('email');

        if ($request->isMethod('POST')) {
            $this->commands->dispatch(new RequestPasswordReset($email));

            // La même réponse dans tous les cas, compte existant ou non :
            // distinguer les deux ferait de ce formulaire un annuaire.
            $sent = true;
        }

        return $this->render('identity/password_request.html.twig', [
            'email' => $email,
            'sent' => $sent,
        ]);
    }
}
