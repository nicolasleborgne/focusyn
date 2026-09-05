<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use App\Identity\Domain\Model\EmailAddress;
use DomainException;

final class EmailAlreadyRegistered extends DomainException
{
    public static function for(EmailAddress $email): self
    {
        return new self(\sprintf('Un compte existe déjà pour %s.', $email->toString()));
    }
}
