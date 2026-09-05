<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

enum OAuthProvider: string
{
    case Google = 'google';
    case GitHub = 'github';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::GitHub => 'GitHub',
        };
    }
}
