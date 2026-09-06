<?php

declare(strict_types=1);

namespace App\Billing\UI\Http;

use App\Billing\Application\Exception\PaymentProviderUnavailable;
use App\Billing\Application\Port\PaymentProvider;
use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Team\TeamSize;
use App\Shared\Application\Tenant\CurrentTenant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use ValueError;

/**
 * Envoie payer chez le prestataire.
 *
 * On ne touche à rien ici : l'abonnement ne change qu'au retour de l'événement
 * signé. Activer à l'aller ferait crédit à un paiement qui n'a pas eu lieu.
 */
final class StartCheckoutController extends AbstractController
{
    public function __construct(
        private readonly PaymentProvider $payments,
        private readonly SubscriptionRepository $subscriptions,
        private readonly TeamSize $team,
        private readonly CurrentTenant $tenant,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/abonnement/payer', 'en' => '/subscription/pay'],
        name: 'billing_checkout',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('billing-checkout')]
    public function __invoke(Request $request): Response
    {
        $organization = $this->tenant->id();

        try {
            $plan = Plan::from($request->request->getString('plan'));
        } catch (ValueError) {
            return $this->redirectToRoute('billing');
        }

        $subscription = $this->subscriptions->ofOrganization($organization);

        try {
            return $this->redirect($this->payments->checkoutUrl(
                $plan,
                $this->team->size(),
                $organization->toString(),
                (string) $this->account->emailOrNull(),
                $subscription?->customerReference(),
            ));
        } catch (PaymentProviderUnavailable) {
            $this->addFlash('error', 'billing.unavailable');

            return $this->redirectToRoute('billing');
        }
    }
}
