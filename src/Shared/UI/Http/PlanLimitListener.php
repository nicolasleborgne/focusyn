<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Application\Billing\PlanLimitReached;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Un plafond atteint se dit, il ne casse pas.
 *
 * Écrit ici une fois plutôt que rattrapé dans chaque contrôleur : la limite
 * peut surgir de n'importe quel cas d'usage, et la réponse est toujours la
 * même — revenir d'où l'on vient, en expliquant.
 *
 * Le message est une clé de traduction : l'écran dit *quoi*, et l'écran
 * d'abonnement dit comment y remédier.
 */
final readonly class PlanLimitListener
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[AsEventListener(event: ExceptionEvent::class)]
    public function __invoke(ExceptionEvent $event): void
    {
        $limit = $this->limitWithin($event->getThrowable());

        if (null === $limit || !$event->getRequest()->hasSession()) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if (method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add('error', $limit->getMessage());
        }

        $event->setResponse(new RedirectResponse($this->backTo($event)));
    }

    /**
     * Le bus déballe déjà les exceptions de transport, mais un gestionnaire
     * d'événement peut encore l'emballer : on redescend la chaîne.
     */
    private function limitWithin(?Throwable $throwable): ?PlanLimitReached
    {
        while (null !== $throwable) {
            if ($throwable instanceof PlanLimitReached) {
                return $throwable;
            }

            $throwable = $throwable->getPrevious();
        }

        return null;
    }

    private function backTo(ExceptionEvent $event): string
    {
        $path = parse_url((string) $event->getRequest()->headers->get('referer'), \PHP_URL_PATH);

        return \is_string($path) && 1 === preg_match('#^/[^/\\\\]#', $path)
            ? $path
            : $this->urls->generate('home');
    }
}
