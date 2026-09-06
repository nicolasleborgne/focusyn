<?php

declare(strict_types=1);

namespace App\Inbox\Infrastructure\Shell;

use App\Inbox\Domain\Repository\CaptureRepository;
use App\Shared\Application\Shell\InboxSummaryProvider;

final readonly class CaptureShellSummary implements InboxSummaryProvider
{
    public function __construct(
        private CaptureRepository $captures,
    ) {
    }

    public function pendingCount(): int
    {
        return $this->captures->count();
    }
}
