<?php

declare(strict_types=1);

namespace App\Billing\UI\Http;

use App\Billing\Application\Exception\PaymentProviderUnavailable;
use App\Billing\Application\Port\PaymentProvider;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Le guichet du prestataire : carte, factures, résiliation.
 *
 * On ne réimplémente rien de tout cela : c'est lui qui garde les moyens de
 * paiement, et qui sait produire une facture conforme.
 */
final class OpenBillingPortalController extends AbstractController
{
    public function __construct(
        private readonly PaymentProvider $payments,
        private readonly SubscriptionRepository $subscriptions,
        private readonly CurrentTenant $tenant,
    ) {
    }

    #[Route(
        path: ['fr' => '/abonnement/guichet', 'en' => '/subscription/portal'],
        name: 'billing_portal',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('billing-portal')]
    public function __invoke(): Response
    {
        $customer = $this->subscriptions->ofOrganization($this->tenant->id())?->customerReference();

        if (null === $customer) {
            return $this->redirectToRoute('billing');
        }

        try {
            return $this->redirect($this->payments->portalUrl($customer));
        } catch (PaymentProviderUnavailable) {
            $this->addFlash('error', 'billing.unavailable');

            return $this->redirectToRoute('billing');
        }
    }
}
