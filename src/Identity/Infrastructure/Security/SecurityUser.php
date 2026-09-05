<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adaptateur entre l'agrégat User et le composant Security.
 *
 * C'est lui, et non l'agrégat, qui implémente `UserInterface` : le domaine
 * n'a pas à connaître les rôles Symfony ni la notion d'identifiant de session.
 * Il ne transporte que ce dont le pare-feu a besoin.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string $email
     */
    public function __construct(
        private string $id,
        private string $email,
        private string $passwordHash,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        $email = $user->email()->toString();
        \assert('' !== $email);

        return new self(
            $user->id()->toString(),
            $email,
            $user->password()->toString(),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        // Les habilitations fines dépendent de l'organisation courante et sont
        // portées par les voteurs du contexte Organization, pas par un rôle
        // global : un même compte peut être propriétaire ici et simple membre
        // ailleurs.
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
