<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\CaptureText;

final readonly class CaptureText
{
    public function __construct(
        public string $text,
        /** `typed_in` ou `shared` — voir `CaptureSource`. */
        public string $source = 'typed_in',
    ) {
    }
}
