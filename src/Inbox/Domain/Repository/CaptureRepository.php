<?php

declare(strict_types=1);

namespace App\Inbox\Domain\Repository;

use App\Inbox\Domain\Model\Capture;
use App\Inbox\Domain\Model\CaptureId;

/**
 * Toutes les requêtes sont cloisonnées par le filtre Doctrine : une capture est
 * `TenantScoped`, et le dépôt n'a donc pas à y penser.
 */
interface CaptureRepository
{
    public function save(Capture $capture): void;

    public function remove(Capture $capture): void;

    public function ofId(CaptureId $id): ?Capture;

    /** @return list<Capture> la plus récente d'abord */
    public function pending(): array;

    public function count(): int;
}
