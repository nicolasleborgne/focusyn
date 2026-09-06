<?php

declare(strict_types=1);

namespace App\Billing\UI\Http;

use App\Billing\Application\Command\ApplyProviderEvent\ApplyProviderEvent;
use App\Shared\Application\Command\CommandBus;
use DateTimeImmutable;
use SensitiveParameter;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use UnexpectedValueException;

/**
 * Ce que Stripe nous raconte.
 *
 * Adresse publique, mais **signée** : c'est la signature qui authentifie, pas
 * la session — Stripe n'en a pas. Sans secret configuré, on refuse tout plutôt
 * que d'accepter n'importe quoi.
 *
 * On répond 200 même à un événement qu'on ne sait pas traiter : Stripe
 * réessaie ce qui échoue, indéfiniment, et un type inconnu n'est pas une panne.
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        #[SensitiveParameter]
        private readonly string $webhookSecret,
    ) {
    }

    #[Route(
        path: '/webhooks/stripe',
        name: 'billing_webhook',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): Response
    {
        if ('' === $this->webhookSecret) {
            return new JsonResponse(['error' => 'not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->headers->get('Stripe-Signature'),
                $this->webhookSecret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return new JsonResponse(['error' => 'invalid_signature'], Response::HTTP_BAD_REQUEST);
        }

        $translated = $this->translate($event->type, (array) $event->data->object->toArray());

        if (null !== $translated) {
            $this->commands->dispatch($translated);
        }

        return new JsonResponse(['received' => true]);
    }

    /**
     * Traduit un événement Stripe dans nos termes.
     *
     * Le reste de l'application ne connaît que quatre faits : activé,
     * renouvelé, paiement échoué, résilié.
     *
     * @param array<string, mixed> $object
     */
    private function translate(string $type, array $object): ?ApplyProviderEvent
    {
        $organization = $this->organizationFrom($object);
        $customer = \is_string($object['customer'] ?? null) ? $object['customer'] : null;

        return match ($type) {
            'checkout.session.completed' => new ApplyProviderEvent(
                kind: 'activated',
                organizationId: $organization ?? (\is_string($object['client_reference_id'] ?? null) ? $object['client_reference_id'] : null),
                customerReference: $customer,
                subscriptionReference: \is_string($object['subscription'] ?? null) ? $object['subscription'] : null,
                // Le palier et l'échéance arrivent avec l'abonnement lui-même,
                // dans l'événement suivant : ici on ne fait que rattacher.
                plan: null,
                periodEndsAt: null,
            ),
            'customer.subscription.created',
            'customer.subscription.updated' => new ApplyProviderEvent(
                kind: 'cancelled' === ($object['status'] ?? null) || ($object['cancel_at_period_end'] ?? false) ? 'cancelled' : 'activated',
                organizationId: $organization,
                customerReference: $customer,
                subscriptionReference: \is_string($object['id'] ?? null) ? $object['id'] : null,
                plan: $this->planFrom($object),
                periodEndsAt: $this->momentFrom($object['current_period_end'] ?? null),
                seats: (int) ($object['items']['data'][0]['quantity'] ?? 1),
            ),
            'customer.subscription.deleted' => new ApplyProviderEvent(
                kind: 'cancelled',
                organizationId: $organization,
                customerReference: $customer,
                subscriptionReference: \is_string($object['id'] ?? null) ? $object['id'] : null,
            ),
            'invoice.payment_failed' => new ApplyProviderEvent(
                kind: 'payment_failed',
                organizationId: $organization,
                customerReference: $customer,
                subscriptionReference: \is_string($object['subscription'] ?? null) ? $object['subscription'] : null,
            ),
            'invoice.paid' => new ApplyProviderEvent(
                kind: 'renewed',
                organizationId: $organization,
                customerReference: $customer,
                subscriptionReference: \is_string($object['subscription'] ?? null) ? $object['subscription'] : null,
                periodEndsAt: $this->momentFrom($object['lines']['data'][0]['period']['end'] ?? null),
            ),
            default => null,
        };
    }

    /** @param array<string, mixed> $object */
    private function organizationFrom(array $object): ?string
    {
        $metadata = $object['metadata'] ?? [];

        return \is_array($metadata) && \is_string($metadata['organization'] ?? null)
            ? $metadata['organization']
            : null;
    }

    /**
     * Le palier se lit dans les métadonnées du prix, posées côté Stripe. À
     * défaut, la quantité tranche : au-delà d'une place, c'est une équipe.
     *
     * @param array<string, mixed> $object
     */
    private function planFrom(array $object): string
    {
        $item = $object['items']['data'][0] ?? null;
        $metadata = \is_array($item) ? ($item['price']['metadata'] ?? []) : [];

        if (\is_array($metadata) && \is_string($metadata['plan'] ?? null)) {
            return $metadata['plan'];
        }

        return ((int) ($item['quantity'] ?? 1)) > 1 ? 'team' : 'personal';
    }

    private function momentFrom(mixed $timestamp): ?DateTimeImmutable
    {
        return \is_int($timestamp) || (\is_string($timestamp) && ctype_digit($timestamp))
            ? (new DateTimeImmutable())->setTimestamp((int) $timestamp)
            : null;
    }
}
