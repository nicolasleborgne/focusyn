<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

/**
 * Ce que la coquille sait de la boîte de réception : combien il reste à trier.
 *
 * Déclaré ici et implémenté par Inbox, comme les autres résumés de la
 * navigation. Une seule primitive : la barre latérale n'a pas à savoir ce
 * qu'est une capture.
 */
interface InboxSummaryProvider
{
    public function pendingCount(): int;
}
