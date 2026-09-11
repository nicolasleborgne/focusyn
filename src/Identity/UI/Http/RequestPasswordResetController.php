<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Command\RequestPasswordReset\RequestPasswordReset;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * « J'ai oublié mon mot de passe ».
 *
 * Le seul écran qui fait partir un courriel vers une adresse nommée dans la
 * requête, sans session ni compte. Il est donc gardé deux fois, et les deux
 * gardes ne protègent pas la même personne :
 *
 *   - le **jeton CSRF** empêche un autre site de déclencher l'envoi depuis le
 *     navigateur de quelqu'un qui passait par là ;
 *   - la **limitation** empêche d'en faire une boucle. Par adresse IP, on
 *     arrête l'auteur et on le lui dit. Par adresse de courriel, on protège
 *     une boîte — et là on ne dit rien : l'écran répond comme d'habitude,
 *     sinon il apprendrait à un inconnu qu'une adresse est inscrite, ce que le
 *     message unique évite justement.
 */
final class RequestPasswordResetController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        #[Autowire(service: 'limiter.password_request_ip')]
        private readonly RateLimiterFactoryInterface $perAddress,
        #[Autowire(service: 'limiter.password_request_email')]
        private readonly RateLimiterFactoryInterface $perMailbox,
    ) {
    }

    #[Route(
        path: ['fr' => '/mot-de-passe-oublie', 'en' => '/forgotten-password'],
        name: 'password_request',
        methods: ['GET', 'POST'],
    )]
    // `methods` est indispensable : sans lui l'attribut vérifie aussi le GET,
    // qui n'a évidemment pas de jeton — l'écran ne s'afficherait plus du tout.
    #[IsCsrfTokenValid('password-request', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $sent = false;
        $email = $request->request->getString('email');
        $throttled = false;

        if ($request->isMethod('POST')) {
            if (!$this->perAddress->create($request->getClientIp())->consume()->isAccepted()) {
                $throttled = true;
            } else {
                // La boîte visée a sa propre mesure. Dépassée, on n'envoie
                // rien et l'écran dit quand même « c'est parti » : la réponse
                // ne doit pas dépendre de ce que l'on sait de l'adresse.
                if ($this->perMailbox->create(mb_strtolower(trim($email)))->consume()->isAccepted()) {
                    $this->commands->dispatch(new RequestPasswordReset($email));
                }

                // La même réponse dans tous les cas, compte existant ou non :
                // distinguer les deux ferait de ce formulaire un annuaire.
                $sent = true;
            }
        }

        return $this->render('identity/password_request.html.twig', [
            'email' => $email,
            'sent' => $sent,
            'throttled' => $throttled,
        ], new Response(status: $throttled ? Response::HTTP_TOO_MANY_REQUESTS : Response::HTTP_OK));
    }
}
