<?php

declare(strict_types=1);

namespace App\Reminder\UI\Http;

use App\Reminder\Application\Command\RotateCalendarFeed\RotateCalendarFeed;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class RotateCalendarFeedController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/calendrier', 'en' => '/settings/calendar'],
        name: 'calendar_feed_rotate',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('calendar-feed-rotate')]
    public function __invoke(): Response
    {
        $this->commands->dispatch(new RotateCalendarFeed());
        $this->addFlash('success', 'reminder.feed.rotated');

        return $this->redirectToRoute('settings');
    }
}
