<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Exception;

use DomainException;

final class TooManyObsessionPoints extends DomainException
{
    public static function limitedTo(int $maximum): self
    {
        return new self(\sprintf(
            'Une obsession se résume en %d points au plus ; au-delà, ce n\'est plus une synthèse.',
            $maximum,
        ));
    }
}
