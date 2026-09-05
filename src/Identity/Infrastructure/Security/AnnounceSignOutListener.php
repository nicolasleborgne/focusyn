<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Annonce la déconnexion sur l'écran de connexion.
 *
 * La maquette y affiche « Vous êtes déconnecté. » : sans ce mot, revenir au
 * formulaire ressemble à un échec de connexion plutôt qu'à une sortie voulue.
 */
#[AsEventListener(event: LogoutEvent::class)]
final readonly class AnnounceSignOutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $session = $event->getRequest()->getSession();

        if ($session instanceof Session) {
            $session->getFlashBag()->add('success', 'auth.signed_out');
        }
    }
}
