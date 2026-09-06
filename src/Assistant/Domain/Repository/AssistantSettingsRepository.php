<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Repository;

use App\Assistant\Domain\Model\AssistantSettings;
use App\Assistant\Domain\Model\OwnerId;

/**
 * Aucun cloisonnement par organisation : les réglages suivent la personne.
 * Toutes les requêtes portent donc un `OwnerId` explicite.
 */
interface AssistantSettingsRepository
{
    public function save(AssistantSettings $settings): void;

    public function remove(AssistantSettings $settings): void;

    public function ofOwner(OwnerId $owner): ?AssistantSettings;
}
