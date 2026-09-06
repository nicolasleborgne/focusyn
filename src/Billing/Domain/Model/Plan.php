<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

/**
 * Ce que chaque palier ouvre.
 *
 * Les limites sont décrites ici, en un seul endroit : c'est ce que le reste de
 * l'application interroge, et le seul lieu où la question « ai-je le droit ? »
 * trouve une réponse.
 *
 * Le gratuit compte les notes et ferme l'assistant et les équipes. Il ne ferme
 * jamais la lecture : un carnet personnel ne doit pas devenir inaccessible
 * parce qu'un paiement a échoué.
 */
enum Plan: string
{
    case Free = 'free';
    case Personal = 'personal';
    case Team = 'team';

    private const int FREE_NOTES = 50;

    /** @return int|null le plafond, ou `null` quand il n'y en a pas */
    public function noteAllowance(): ?int
    {
        return self::Free === $this ? self::FREE_NOTES : null;
    }

    public function allowsAssistant(): bool
    {
        return self::Free !== $this;
    }

    public function allowsTeams(): bool
    {
        return self::Free !== $this;
    }

    public function isPaid(): bool
    {
        return self::Free !== $this;
    }

    /** Seule l'équipe se facture au membre ; le personnel est à prix fixe. */
    public function isBilledPerSeat(): bool
    {
        return self::Team === $this;
    }
}
