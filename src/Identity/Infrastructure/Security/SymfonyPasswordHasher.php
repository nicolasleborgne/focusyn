<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\HashedPassword;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Adaptateur du composant PasswordHasher.
 *
 * Passe par la fabrique plutôt que par `UserPasswordHasherInterface` : ce
 * dernier exige un objet `PasswordAuthenticatedUserInterface`, or au moment de
 * l'inscription le compte n'existe pas encore.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(
        private PasswordHasherFactoryInterface $hashers,
    ) {
    }

    public function hash(#[SensitiveParameter] string $plainPassword): HashedPassword
    {
        return HashedPassword::fromHash(
            $this->hashers->getPasswordHasher(SecurityUser::class)->hash($plainPassword),
        );
    }

    public function verify(HashedPassword $hashed, #[SensitiveParameter] string $plainPassword): bool
    {
        return $this->hashers
            ->getPasswordHasher(SecurityUser::class)
            ->verify($hashed->toString(), $plainPassword);
    }
}
