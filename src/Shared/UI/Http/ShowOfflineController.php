<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page servie par le service worker quand le réseau manque.
 *
 * Volontairement autonome : elle ne rend pas la coquille, dont les compteurs
 * dépendront un jour de la base. Une page de secours qui a besoin du serveur
 * n'est pas une page de secours.
 */
final class ShowOfflineController extends AbstractController
{
    #[Route(path: '/offline', name: 'offline', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('offline.html.twig');
    }
}
