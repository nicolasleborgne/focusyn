<?php

declare(strict_types=1);

namespace App\Reminder\UI\Http;

use App\Reminder\Application\Command\ForgetDevice\ForgetDevice;
use App\Reminder\Application\Command\RegisterDevice\RegisterDevice;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * L'abonnement d'un navigateur, pris et rendu.
 *
 * Appelé en JSON par le navigateur, qui vient d'obtenir son point de réception
 * : le jeton CSRF voyage donc dans un en-tête, comme pour la sauvegarde de
 * l'éditeur.
 */
final class RegisterDeviceController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route(
        path: ['fr' => '/rappels/abonnement', 'en' => '/reminders/subscription'],
        name: 'reminder_device',
        methods: ['POST', 'DELETE'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->csrf->isTokenValid(new CsrfToken('reminder-device', (string) $request->headers->get('X-CSRF-Token')))) {
            return new JsonResponse(['error' => 'invalid_csrf_token'], Response::HTTP_FORBIDDEN);
        }

        try {
            /** @var array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}} $payload */
            $payload = json_decode((string) $request->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse(['error' => 'malformed_payload'], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = $payload['endpoint'] ?? null;

        if (!\is_string($endpoint)) {
            return new JsonResponse(['error' => 'endpoint_expected'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commands->dispatch($request->isMethod('DELETE')
                ? new ForgetDevice($endpoint)
                : new RegisterDevice(
                    $endpoint,
                    (string) ($payload['keys']['p256dh'] ?? ''),
                    (string) ($payload['keys']['auth'] ?? ''),
                ));
        } catch (InvalidArgumentException $error) {
            return new JsonResponse(['error' => $error->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['ok' => true]);
    }
}
