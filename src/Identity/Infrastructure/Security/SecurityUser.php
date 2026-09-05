<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adaptateur entre l'agrégat User et le composant Security.
 *
 * C'est lui, et non l'agrégat, qui implémente `UserInterface` et les interfaces
 * du bundle de double authentification : le domaine n'a pas à connaître les
 * rôles Symfony, ni la notion de session, ni un format de configuration TOTP.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    /**
     * @param non-empty-string $email
     * @param list<string>     $backupCodeHashes
     */
    public function __construct(
        private readonly string $id,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly ?string $totpSecret = null,
        private array $backupCodeHashes = [],
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
            $user->totpSecret()?->toString(),
            $user->backupCodes(),
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

    // ---- double authentification ------------------------------------------

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        // Paramètres standards : SHA-1, 6 chiffres, fenêtre de 30 secondes.
        // Tout écart casserait la compatibilité avec les applications courantes.
        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    /**
     * Les codes de secours étant stockés hachés, la comparaison ne peut pas se
     * faire ici : elle est confiée au gestionnaire dédié, qui dispose du
     * vérificateur d'empreintes. Voir HashedBackupCodeManager.
     */
    public function isBackupCode(string $code): bool
    {
        return false;
    }

    public function invalidateBackupCode(string $code): void
    {
    }

    /** @return list<string> */
    public function backupCodeHashes(): array
    {
        return $this->backupCodeHashes;
    }
}
