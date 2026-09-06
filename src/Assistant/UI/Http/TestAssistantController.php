<?php

declare(strict_types=1);

namespace App\Assistant\UI\Http;

use App\Assistant\Application\Command\TestAssistant\TestAssistant;
use App\Assistant\Application\Exception\AssistantRefused;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class TestAssistantController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/assistant/test', 'en' => '/settings/assistant/test'],
        name: 'assistant_test',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('assistant-test')]
    public function __invoke(): Response
    {
        try {
            $report = $this->commands->dispatch(new TestAssistant());
            $this->addFlash('assistant_test', \is_array($report)
                ? \sprintf('%s · %d ms', $report['model'], $report['milliseconds'])
                : '');
        } catch (AssistantRefused $refusal) {
            $this->addFlash('assistant_error', $refusal->getMessage());
        }

        return $this->redirectToRoute('settings');
    }
}
