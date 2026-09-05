<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Écran de connexion.
 *
 * La vérification des identifiants est faite par le pare-feu, pas ici : ce
 * contrôleur ne fait qu'afficher le formulaire et la dernière erreur.
 */
final class ShowLoginController extends AbstractController
{
    #[Route(
        path: ['fr' => '/connexion', 'en' => '/login'],
        name: 'login',
        methods: ['GET', 'POST'],
    )]
    public function __invoke(AuthenticationUtils $authentication): Response
    {
        if ($this->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            return $this->redirectToRoute('home');
        }

        return $this->render('identity/login.html.twig', [
            'lastEmail' => $authentication->getLastUsername(),
            'error' => $authentication->getLastAuthenticationError(),
        ]);
    }
}
