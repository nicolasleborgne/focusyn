<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use DomainException;

final class UnverifiedOAuthEmail extends DomainException
{
    public static function create(): self
    {
        return new self(
            'Ce fournisseur ne garantit pas que cette adresse vous appartient ; '
            .'connectez-vous par mot de passe, puis rattachez le fournisseur depuis vos réglages.',
        );
    }
}
