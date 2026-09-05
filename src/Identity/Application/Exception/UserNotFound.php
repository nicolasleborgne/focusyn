<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use App\Identity\Domain\Model\UserId;
use DomainException;

final class UserNotFound extends DomainException
{
    public static function withId(UserId $id): self
    {
        return new self(\sprintf('Aucun compte pour l\'identifiant %s.', $id->toString()));
    }
}
