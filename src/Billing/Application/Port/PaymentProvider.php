<?php

declare(strict_types=1);

namespace App\Billing\Application\Port;

use App\Billing\Domain\Model\Plan;

/**
 * Le prestataire de paiement, vu de l'application.
 *
 * Deux adresses seulement : celle où l'on va payer, celle où l'on va gérer sa
 * carte et ses factures. Tout le reste — statuts, périodes, prorata — nous
 * revient par les événements qu'il envoie.
 *
 * Non configuré, il le dit plutôt que d'échouer : l'écran cache alors ses
 * boutons, comme pour un fournisseur externe absent.
 */
interface PaymentProvider
{
    public function isConfigured(): bool;

    /** @return string l'adresse où poursuivre le paiement */
    public function checkoutUrl(Plan $plan, int $seats, string $organizationId, string $email, ?string $customerReference): string;

    /** @return string l'adresse du guichet où gérer carte, factures et résiliation */
    public function portalUrl(string $customerReference): string;
}
