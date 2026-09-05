<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Port\SessionStarter;
use App\Identity\Domain\Model\UserId;
use App\Identity\UI\Form\RegistrationData;
use App\Identity\UI\Form\RegistrationForm;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly SessionStarter $sessions,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: ['fr' => '/inscription', 'en' => '/register'],
        name: 'register',
        methods: ['GET', 'POST'],
    )]
    public function __invoke(Request $request): Response
    {
        if ($this->getUser() instanceof UserInterface) {
            return $this->redirectToRoute('home');
        }

        $data = new RegistrationData();
        $form = $this->createForm(RegistrationForm::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $userId = $this->commands->dispatch(new RegisterUser(
                    (string) $data->email,
                    (string) $data->plainPassword,
                ));

                // L'inscription vaut authentification : redemander le mot de
                // passe qu'on vient de choisir est une friction sans
                // contrepartie de sécurité.
                if ($userId instanceof UserId) {
                    $this->sessions->signIn($userId);
                }

                return $this->redirectToRoute('home');
            } catch (EmailAlreadyRegistered) {
                $form->get('email')->addError(new FormError($this->translator->trans('registration.email.taken')));
            }
        }

        return $this->render('identity/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
