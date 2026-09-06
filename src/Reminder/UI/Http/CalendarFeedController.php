<?php

declare(strict_types=1);

namespace App\Reminder\UI\Http;

use App\Reminder\Application\Calendar\IcsCalendar;
use App\Reminder\Application\Query\ReminderQuery;
use App\Reminder\Domain\Model\FeedToken;
use App\Reminder\Domain\Repository\CalendarFeedRepository;
use App\Shared\Application\Tenant\TenantScope;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Le flux auquel un agenda s'abonne.
 *
 * Adresse publique : un agenda ne se connecte pas, il récupère une URL toutes
 * les quelques heures. Le jeton tient donc lieu d'authentification — on le
 * résout d'abord, puis on lit les rappels *dans* son organisation, via
 * `TenantScope`, plutôt que de désarmer le cloisonnement.
 */
final class CalendarFeedController extends AbstractController
{
    public function __construct(
        private readonly CalendarFeedRepository $feeds,
        private readonly ReminderQuery $reminders,
        private readonly IcsCalendar $calendar,
        private readonly TenantScope $scope,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(
        path: ['fr' => '/calendrier/{token}.ics', 'en' => '/calendar/{token}.ics'],
        name: 'calendar_feed',
        requirements: ['token' => '[A-Za-z0-9_-]{43}'],
        methods: ['GET'],
    )]
    public function __invoke(string $token): Response
    {
        try {
            $feed = $this->feeds->ofToken(FeedToken::fromString($token));
        } catch (InvalidArgumentException) {
            $feed = null;
        }

        if (null === $feed) {
            throw $this->createNotFoundException();
        }

        $reminders = $this->scope->runAs(
            $feed->organizationId(),
            fn (): array => $this->reminders->all(),
        );

        $body = $this->calendar->render(
            \is_array($reminders) ? array_values($reminders) : [],
            $this->clock->now(),
            'Focusyn',
        );

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            // Un agenda repasse souvent : rien ne sert de lui servir du cache.
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
