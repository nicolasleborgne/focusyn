<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\CancelEmailChange\CancelEmailChange;
use App\Identity\Application\Command\RequestEmailChange\RequestEmailChange;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ChangeEmailController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/adresse', 'en' => '/settings/address'],
        name: 'email_change_request',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('email-change')]
    public function __invoke(Request $request): Response
    {
        if ($request->request->has('cancel')) {
            $this->commands->dispatch(new CancelEmailChange());
            $this->addFlash('success', 'email_change.cancelled');

            return $this->redirectToRoute('settings');
        }

        try {
            $this->commands->dispatch(new RequestEmailChange($request->request->getString('email')));
            $this->addFlash('success', 'email_change.requested');
        } catch (EmailAlreadyRegistered) {
            // On le dit : l'adresse est saisie par son propriétaire présumé, et
            // taire l'occupation ne ferait que le laisser attendre un courriel
            // qui n'arriverait jamais.
            $this->addFlash('error', 'email_change.taken');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'email_change.refused');
        }

        return $this->redirectToRoute('settings');
    }
}
