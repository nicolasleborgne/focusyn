<?php

declare(strict_types=1);

namespace App\Reminder\UI\Http;

use App\Reminder\Application\Calendar\IcsCalendar;
use App\Reminder\Application\Query\ReminderQuery;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Un événement à déposer dans l'agenda du système.
 *
 * Un téléchargement et non un abonnement : l'événement part une fois, le
 * calendrier en devient propriétaire. Pour suivre les échéances dans la durée,
 * c'est le flux qu'il faut (voir `CalendarFeedController`).
 */
final class DownloadReminderController extends AbstractController
{
    public function __construct(
        private readonly ReminderQuery $reminders,
        private readonly IcsCalendar $calendar,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(
        path: ['fr' => '/rappels/{id}.ics', 'en' => '/reminders/{id}.ics'],
        name: 'reminder_ics',
        requirements: ['id' => '[0-9a-f-]{36}'],
        methods: ['GET'],
    )]
    public function __invoke(string $id): Response
    {
        $reminder = $this->reminders->forId($id) ?? throw $this->createNotFoundException();

        $body = $this->calendar->render([$reminder], $this->clock->now(), 'Focusyn');

        $response = new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/calendar; charset=utf-8']);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition('attachment', 'focusyn-rappel.ics'),
        );

        return $response;
    }
}
