<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Domain\Model\OAuthProvider;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

/**
 * Adresse de retour du fournisseur.
 *
 * Le corps n'est jamais exécuté : l'authentificateur intercepte la requête.
 * La route doit exister pour être déclarée comme URL de redirection.
 */
final class CheckOAuthController extends AbstractController
{
    #[Route(
        path: ['fr' => '/connexion/{provider}/retour', 'en' => '/login/{provider}/callback'],
        name: 'oauth_check',
        requirements: ['provider' => new EnumRequirement(OAuthProvider::class)],
        methods: ['GET'],
    )]
    public function __invoke(): never
    {
        throw new LogicException('Cette route est interceptée par l\'authentificateur OAuth.');
    }
}
