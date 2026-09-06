<?php

declare(strict_types=1);

namespace App\Assistant\UI\Http;

use App\Assistant\Application\Command\AdjustAssistant\AdjustAssistant;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use ValueError;

final class AdjustAssistantController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/reglages/assistant', 'en' => '/settings/assistant'],
        name: 'assistant_adjust',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('assistant-adjust')]
    public function __invoke(Request $request): Response
    {
        try {
            $this->commands->dispatch(new AdjustAssistant(
                provider: self::stringOrNull($request, 'provider'),
                apiKey: $request->request->has('apiKey') ? $request->request->getString('apiKey') : null,
                model: self::stringOrNull($request, 'model'),
                baseUrl: self::stringOrNull($request, 'baseUrl'),
                wholeNote: $request->request->has('wholeNote') ? $request->request->getBoolean('wholeNote') : null,
            ));
            $this->addFlash('success', 'assistant.saved');
        } catch (ValueError|InvalidArgumentException) {
            $this->addFlash('error', 'assistant.rejected');
        }

        return $this->redirectToRoute('settings');
    }

    private static function stringOrNull(Request $request, string $field): ?string
    {
        return null === $request->request->get($field) ? null : $request->request->getString($field);
    }
}
