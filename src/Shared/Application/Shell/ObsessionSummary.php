<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

/**
 * Une obsession telle que la coquille a besoin de la connaître : un nom, une
 * adresse et un volume. Rien de plus — la coquille n'a pas à charger les notes.
 */
final readonly class ObsessionSummary
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $noteCount,
    ) {
    }
}
