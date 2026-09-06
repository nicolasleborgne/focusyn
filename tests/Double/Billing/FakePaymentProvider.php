<?php

declare(strict_types=1);

namespace App\Tests\Double\Billing;

use App\Billing\Application\Exception\PaymentProviderUnavailable;
use App\Billing\Application\Port\PaymentProvider;
use App\Billing\Domain\Model\Plan;

/**
 * Le prestataire de paiement, simulé.
 *
 * Il remplace Stripe en environnement de test : aucun appel ne doit sortir de
 * la machine parce qu'une suite tourne, et surtout aucune clé n'est nécessaire
 * pour vérifier ce qui nous appartient — que le bouton apparaisse, que le bon
 * palier parte, que le nombre de places suive.
 *
 * Il retient ce qu'on lui a demandé : c'est justement ce qu'on veut vérifier.
 */
final class FakePaymentProvider implements PaymentProvider
{
    public bool $configured = true;
    public bool $unavailable = false;

    public ?Plan $lastPlan = null;
    public ?int $lastSeats = null;
    public ?string $lastOrganization = null;
    public ?string $lastEmail = null;
    public ?string $lastCustomer = null;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function checkoutUrl(Plan $plan, int $seats, string $organizationId, string $email, ?string $customerReference): string
    {
        if ($this->unavailable) {
            throw PaymentProviderUnavailable::because('Le double refuse de répondre.');
        }

        $this->lastPlan = $plan;
        $this->lastSeats = $seats;
        $this->lastOrganization = $organizationId;
        $this->lastEmail = $email;
        $this->lastCustomer = $customerReference;

        return 'https://paiement.exemple/checkout/'.$plan->value;
    }

    public function portalUrl(string $customerReference): string
    {
        if ($this->unavailable) {
            throw PaymentProviderUnavailable::because('Le double refuse de répondre.');
        }

        $this->lastCustomer = $customerReference;

        return 'https://paiement.exemple/portail/'.$customerReference;
    }
}
