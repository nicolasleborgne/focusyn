<?php

declare(strict_types=1);

namespace App\Billing\UI\Http;

use App\Billing\Application\Query\BillingQuery;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowBillingController extends AbstractController
{
    public function __construct(
        private readonly BillingQuery $billing,
    ) {
    }

    #[Route(
        path: ['fr' => '/abonnement', 'en' => '/subscription'],
        name: 'billing',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): Response
    {
        return $this->render('billing/subscription.html.twig', [
            'section' => ShellSection::Settings,
            'billing' => $this->billing->current() ?? throw $this->createNotFoundException(),
            'plans' => $this->billing->offered(),
            // Retour du guichet de paiement. Rien n'est activé ici : c'est le
            // webhook signé qui fait foi, et il arrive une seconde plus tard.
            // Sans ce mot, l'écran afficherait l'ancien palier sans rien dire,
            // et l'on croirait que le paiement n'a pas pris.
            'justPaid' => $request->query->has('paye'),
        ]);
    }
}
