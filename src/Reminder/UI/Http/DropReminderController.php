<?php

declare(strict_types=1);

namespace App\Reminder\UI\Http;

use App\Reminder\Application\Command\DropReminder\DropReminder;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class DropReminderController extends AbstractController
{
    use BackToWhereWeCameFrom;

    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/rappels/supprimer', 'en' => '/reminders/drop'],
        name: 'reminder_drop',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('reminder-drop')]
    public function __invoke(Request $request): Response
    {
        try {
            $this->commands->dispatch(new DropReminder($request->request->getString('subject')));
            $this->addFlash('success', 'reminder.dropped');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'reminder.invalid');
        }

        return $this->redirect($this->backTo($request));
    }
}
