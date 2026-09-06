<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Model;

enum Retention: string
{
    case TwelveMonths = 'twelve_months';
    case TwentyFourMonths = 'twenty_four_months';
    case Unlimited = 'unlimited';

    /** Null quand la conservation est sans limite. */
    public function months(): ?int
    {
        return match ($this) {
            self::TwelveMonths => 12,
            self::TwentyFourMonths => 24,
            self::Unlimited => null,
        };
    }
}
