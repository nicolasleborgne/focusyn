<?php

declare(strict_types=1);

namespace App\Billing\Application\Exception;

use RuntimeException;

final class PaymentProviderUnavailable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
