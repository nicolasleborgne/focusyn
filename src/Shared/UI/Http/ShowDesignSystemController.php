<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page de référence du design system.
 *
 * Rend chaque bloc BEM avec ses variantes, sur un seul écran, afin de vérifier
 * d'un coup d'œil qu'un changement de jeton n'a rien cassé ailleurs. Routée en
 * développement seulement.
 */
final class ShowDesignSystemController extends AbstractController
{
    #[Route(path: '/_design-system', name: 'design_system', methods: ['GET'], env: 'dev')]
    public function __invoke(): Response
    {
        return $this->render('design_system/index.html.twig', [
            'accents' => [
                'ink' => 'Encre',
                'slate' => 'Ardoise',
                'olive' => 'Olive',
                'tobacco' => 'Tabac',
                'brick' => 'Brique',
            ],
        ]);
    }
}
