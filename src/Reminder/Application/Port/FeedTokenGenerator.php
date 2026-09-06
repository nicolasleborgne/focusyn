<?php

declare(strict_types=1);

namespace App\Reminder\Application\Port;

use App\Reminder\Domain\Model\FeedToken;

interface FeedTokenGenerator
{
    public function generate(): FeedToken;
}
