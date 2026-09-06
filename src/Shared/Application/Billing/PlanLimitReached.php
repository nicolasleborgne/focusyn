<?php

declare(strict_types=1);

namespace App\Shared\Application\Billing;

use RuntimeException;

/**
 * Un plafond du palier gratuit est atteint.
 *
 * Le message est une clé de traduction : l'écran doit pouvoir dire *quoi*, et
 * proposer d'y remédier plutôt que d'annoncer un échec.
 */
final class PlanLimitReached extends RuntimeException
{
    public static function notes(int $allowance): self
    {
        return new self('billing.limit.notes');
    }

    public static function assistant(): self
    {
        return new self('billing.limit.assistant');
    }

    public static function teams(): self
    {
        return new self('billing.limit.teams');
    }

    public static function members(): self
    {
        return new self('billing.limit.members');
    }
}
