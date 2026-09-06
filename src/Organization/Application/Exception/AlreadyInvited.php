<?php

declare(strict_types=1);

namespace App\Organization\Application\Exception;

use RuntimeException;

final class AlreadyInvited extends RuntimeException
{
    public static function withEmail(string $email): self
    {
        return new self(\sprintf('%s a déjà une invitation en cours.', $email));
    }
}
