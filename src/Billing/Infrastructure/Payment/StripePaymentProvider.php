<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Payment;

use App\Billing\Application\Exception\PaymentProviderUnavailable;
use App\Billing\Application\Port\PaymentProvider;
use App\Billing\Domain\Model\Plan;
use SensitiveParameter;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Stripe.
 *
 * L'organisation voyage dans les métadonnées de la session : c'est elle que le
 * webhook retrouvera pour savoir quel abonnement activer. Sans cela, il
 * faudrait deviner à qui appartient un paiement.
 */
final readonly class StripePaymentProvider implements PaymentProvider
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        #[SensitiveParameter]
        private string $secretKey,
        private string $personalPrice,
        private string $teamPrice,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->secretKey && '' !== $this->personalPrice && '' !== $this->teamPrice;
    }

    public function checkoutUrl(Plan $plan, int $seats, string $organizationId, string $email, ?string $customerReference): string
    {
        $price = $this->priceFor($plan);

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $price,
                    // Le personnel est à prix fixe ; l'équipe se facture au
                    // membre, et Stripe ajuste au prorata lors des changements.
                    'quantity' => $plan->isBilledPerSeat() ? max(1, $seats) : 1,
                ]],
                'client_reference_id' => $organizationId,
                'metadata' => ['organization' => $organizationId],
                'subscription_data' => ['metadata' => ['organization' => $organizationId]],
                ...(null === $customerReference ? ['customer_email' => $email] : ['customer' => $customerReference]),
                'success_url' => $this->urls->generate('billing', [], UrlGeneratorInterface::ABSOLUTE_URL).'?paye=1',
                'cancel_url' => $this->urls->generate('billing', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        } catch (ApiErrorException $error) {
            throw PaymentProviderUnavailable::because($error->getMessage());
        }

        return (string) $session->url;
    }

    public function portalUrl(string $customerReference): string
    {
        try {
            $session = $this->client()->billingPortal->sessions->create([
                'customer' => $customerReference,
                'return_url' => $this->urls->generate('billing', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        } catch (ApiErrorException $error) {
            throw PaymentProviderUnavailable::because($error->getMessage());
        }

        return (string) $session->url;
    }

    private function priceFor(Plan $plan): string
    {
        return match ($plan) {
            Plan::Personal => $this->personalPrice,
            Plan::Team => $this->teamPrice,
            Plan::Free => throw PaymentProviderUnavailable::because('Le palier gratuit ne se paie pas.'),
        };
    }

    private function client(): StripeClient
    {
        if (!$this->isConfigured()) {
            throw PaymentProviderUnavailable::because('Aucune clé Stripe configurée.');
        }

        return new StripeClient($this->secretKey);
    }
}
