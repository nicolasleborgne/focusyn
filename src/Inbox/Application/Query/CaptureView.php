<?php

declare(strict_types=1);

namespace App\Inbox\Application\Query;

use DateTimeImmutable;

final readonly class CaptureView
{
    public function __construct(
        public string $id,
        /** `link` ou `text` : sert de clé de traduction pour l'étiquette. */
        public string $kind,
        /** `typed_in` ou `shared` : clé de traduction, elle aussi. */
        public string $source,
        public string $title,
        public string $excerpt,
        public DateTimeImmutable $capturedAt,
    ) {
    }
}
