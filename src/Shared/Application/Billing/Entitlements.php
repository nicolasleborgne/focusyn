<?php

declare(strict_types=1);

namespace App\Shared\Application\Billing;

/**
 * Ce que l'organisation courante a le droit de faire.
 *
 * Déclaré ici et implémenté dans Billing : Notebook, Assistant et Organization
 * doivent pouvoir demander « ai-je le droit ? » sans connaître ce qu'est un
 * abonnement, ni un palier, ni un prestataire de paiement.
 *
 * Rien ici ne ferme la lecture. Un plafond atteint arrête l'écriture ; il ne
 * rend jamais un carnet inaccessible.
 */
interface Entitlements
{
    public function allowsAssistant(): bool;

    public function allowsTeams(): bool;

    /** @return int|null le plafond de notes, ou `null` quand il n'y en a pas */
    public function noteAllowance(): ?int;
}
