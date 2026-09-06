<?php

declare(strict_types=1);

namespace App\Shared\Application\Privacy;

/**
 * A-t-on le droit de faire cela pour cette personne ?
 *
 * Déclaré ici et implémenté dans Privacy : l'assistant doit vérifier le
 * consentement avant d'envoyer quoi que ce soit à un tiers, sans avoir le droit
 * de connaître le contexte qui le conserve.
 *
 * Le refus est la valeur par défaut : sans réponse, on ne fait rien.
 */
interface ConsentGate
{
    public function allows(string $consent, string $accountId): bool;
}
