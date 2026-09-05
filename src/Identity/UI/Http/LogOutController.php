<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'ancrage de la déconnexion.
 *
 * Le corps n'est jamais exécuté : le pare-feu intercepte la requête en amont.
 * La route doit néanmoins exister pour être générée dans les gabarits.
 */
final class LogOutController extends AbstractController
{
    #[Route(
        path: ['fr' => '/deconnexion', 'en' => '/logout'],
        name: 'logout',
        methods: ['POST'],
    )]
    public function __invoke(): never
    {
        throw new LogicException('Cette route est interceptée par le pare-feu de sécurité.');
    }
}
