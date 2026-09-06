<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

/**
 * Une organisation dont on est membre, telle que le sélecteur la présente.
 */
final readonly class SpaceEntry
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $personal,
        public bool $current,
    ) {
    }
}
