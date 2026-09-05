<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\BackupCodeHasher;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use Scheb\TwoFactorBundle\Security\TwoFactor\Backup\BackupCodeManagerInterface;
use SensitiveParameter;

/**
 * Vérifie et consomme un code de secours haché.
 *
 * Le bundle interroge par défaut l'objet utilisateur lui-même, ce qui suppose
 * des codes en clair. Comme ils sont stockés hachés, la comparaison passe par
 * ce service, seul à disposer du vérificateur d'empreintes et du dépôt.
 */
final readonly class HashedBackupCodeManager implements BackupCodeManagerInterface
{
    public function __construct(
        private UserRepository $users,
        private BackupCodeHasher $hasher,
    ) {
    }

    public function isBackupCode(object $user, string $code): bool
    {
        if (!$user instanceof SecurityUser) {
            return false;
        }

        return null !== $this->matchingHash($user, $code);
    }

    public function invalidateBackupCode(object $user, string $code): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        $hash = $this->matchingHash($user, $code);

        if (null === $hash) {
            return;
        }

        $domainUser = $this->users->ofEmail(EmailAddress::fromString($user->getUserIdentifier()));

        if (null === $domainUser) {
            return;
        }

        // Un code de secours ne sert qu'une fois : c'est toute sa raison d'être.
        $domainUser->revokeBackupCode($hash);
        $this->users->save($domainUser);
    }

    private function matchingHash(SecurityUser $user, #[SensitiveParameter] string $code): ?string
    {
        foreach ($user->backupCodeHashes() as $hash) {
            if ($this->hasher->verify($hash, $code)) {
                return $hash;
            }
        }

        return null;
    }
}
