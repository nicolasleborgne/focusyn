<?php

declare(strict_types=1);

namespace App\Inbox\Application\Exception;

use RuntimeException;

final class CaptureNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Aucune capture « %s » dans cette boîte.', $id));
    }
}
