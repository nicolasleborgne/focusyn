<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Session;

use App\Identity\Application\Port\SessionCloser;
use SessionHandlerInterface;

final readonly class HandlerSessionCloser implements SessionCloser
{
    public function __construct(
        private SessionHandlerInterface $sessions,
    ) {
    }

    public function close(string $sessionId): void
    {
        $this->sessions->destroy($sessionId);
    }
}
