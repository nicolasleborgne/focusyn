<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\HashedPassword;
use SensitiveParameter;

/**
 * Le domaine ne hache pas : l'algorithme et son coût sont des décisions
 * d'infrastructure, qui changent avec le matériel et l'état de l'art.
 */
interface PasswordHasher
{
    public function hash(#[SensitiveParameter] string $plainPassword): HashedPassword;

    public function verify(HashedPassword $hashed, #[SensitiveParameter] string $plainPassword): bool;
}
