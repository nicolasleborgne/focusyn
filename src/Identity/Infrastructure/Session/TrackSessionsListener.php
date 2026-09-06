<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Session;

use App\Identity\Domain\Model\Device;
use App\Identity\Domain\Model\LoginSession;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\LoginSessionRepository;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Application\Account\CurrentAccount;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Tient à jour la liste des sessions ouvertes.
 *
 * Une entrée à la connexion, puis une date de dernière vue rafraîchie au plus
 * une fois par quart d'heure : marquer chaque requête écrirait en base à chaque
 * clic pour une information dont la précision n'intéresse personne.
 */
final readonly class TrackSessionsListener
{
    private const int REFRESH_AFTER_SECONDS = 900;

    public function __construct(
        private LoginSessionRepository $sessions,
        private CurrentAccount $account,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener(event: InteractiveLoginEvent::class)]
    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $request = $event->getRequest();

        if (!$user instanceof SecurityUser || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        // L'identifiant change à la connexion (protection contre la fixation) :
        // c'est celui d'après qu'il faut retenir.
        if (!$session->isStarted()) {
            $session->start();
        }

        $this->sessions->save(LoginSession::open(
            $session->getId(),
            UserId::fromString($user->id()),
            Device::fromUserAgent((string) $request->headers->get('User-Agent')),
            $this->clock->now(),
        ));
    }

    #[AsEventListener(event: RequestEvent::class, priority: -32)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->account->isAuthenticated()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasSession() || !$request->getSession()->isStarted()) {
            return;
        }

        $known = $this->sessions->ofId($request->getSession()->getId());
        $now = $this->clock->now();

        if (null === $known || $now->getTimestamp() - $known->lastSeenAt()->getTimestamp() < self::REFRESH_AFTER_SECONDS) {
            return;
        }

        $known->seenAt($now);
        $this->sessions->save($known);
    }
}
