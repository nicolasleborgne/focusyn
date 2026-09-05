<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

enum Density: string
{
    case Comfortable = 'comfortable';
    case Compact = 'compact';
}
