<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

use DateTimeImmutable;

/**
 * Une session ouverte, telle que l'écran des réglages la présente.
 *
 * `current` désigne celle depuis laquelle on regarde : elle ne se ferme pas
 * depuis cette liste — on s'en déconnecte.
 */
final readonly class SessionEntry
{
    public function __construct(
        public string $id,
        public string $device,
        public DateTimeImmutable $lastSeenAt,
        public bool $current,
    ) {
    }
}
