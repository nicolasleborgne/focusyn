<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

use DomainException;

final class InvitationCannotBeAccepted extends DomainException
{
    public static function becauseItWasAlreadyAccepted(): self
    {
        return new self('Cette invitation a déjà été acceptée.');
    }

    public static function becauseItExpired(): self
    {
        return new self('Cette invitation a expiré.');
    }

    public static function becauseItWasAddressedToSomeoneElse(): self
    {
        return new self('Cette invitation ne vous est pas adressée.');
    }
}
