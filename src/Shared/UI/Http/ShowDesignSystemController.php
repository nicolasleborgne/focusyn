<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Page de référence du design system.
 *
 * Rend chaque bloc BEM avec ses variantes, sur un seul écran, afin de vérifier
 * d'un coup d'œil qu'un changement de jeton n'a rien cassé ailleurs. Elle n'est
 * routée qu'en environnement de développement (voir config/routes/design_system.yaml).
 */
final readonly class ShowDesignSystemController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    #[Route(path: '/_design-system', name: 'design_system', methods: ['GET'], env: 'dev')]
    public function __invoke(): Response
    {
        return new Response($this->twig->render('design_system/index.html.twig', [
            'accents' => [
                'ink' => 'Encre',
                'slate' => 'Ardoise',
                'olive' => 'Olive',
                'tobacco' => 'Tabac',
                'brick' => 'Brique',
            ],
        ]));
    }
}
