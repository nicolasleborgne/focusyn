<?php

declare(strict_types=1);

namespace App\Billing\Application\Command\ApplyProviderEvent;

use DateTimeImmutable;

/**
 * Ce qu'un événement du prestataire nous apprend, traduit dans nos termes.
 *
 * Le contrôleur du webhook lit Stripe ; le cas d'usage n'en sait rien. C'est ce
 * qui permet de le tester sans réseau, et de changer de prestataire sans
 * toucher au domaine.
 */
final readonly class ApplyProviderEvent
{
    public function __construct(
        public string $kind,
        public ?string $organizationId,
        public ?string $customerReference,
        public ?string $subscriptionReference,
        public ?string $plan = null,
        public ?DateTimeImmutable $periodEndsAt = null,
        public int $seats = 1,
    ) {
    }
}
