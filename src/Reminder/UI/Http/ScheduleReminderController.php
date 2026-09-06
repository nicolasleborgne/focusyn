<?php

declare(strict_types=1);

namespace App\Reminder\UI\Http;

use App\Reminder\Application\Command\ScheduleReminder\ScheduleReminder;
use App\Reminder\Application\Exception\InvalidReminderMoment;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ScheduleReminderController extends AbstractController
{
    use BackToWhereWeCameFrom;

    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/rappels', 'en' => '/reminders'],
        name: 'reminder_schedule',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('reminder-schedule')]
    public function __invoke(Request $request): Response
    {
        try {
            $this->commands->dispatch(new ScheduleReminder(
                subject: $request->request->getString('subject'),
                label: $request->request->getString('label'),
                dueAt: $request->request->getString('date').' '.$request->request->getString('time'),
            ));
            $this->addFlash('success', 'reminder.saved');
        } catch (InvalidReminderMoment|InvalidArgumentException) {
            $this->addFlash('error', 'reminder.invalid');
        }

        return $this->redirect($this->backTo($request));
    }
}
