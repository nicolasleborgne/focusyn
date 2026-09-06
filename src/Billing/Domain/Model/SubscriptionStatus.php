<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

/**
 * Où en est la relation commerciale.
 *
 * À distinguer du palier : le statut dit *pourquoi* on a droit à quelque
 * chose, le palier dit *à quoi*.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    /** Un prélèvement a échoué : on retombe au gratuit, sans rien effacer. */
    case PastDue = 'past_due';
    /** Résilié : la période payée court jusqu'à son terme. */
    case Cancelled = 'cancelled';
}
