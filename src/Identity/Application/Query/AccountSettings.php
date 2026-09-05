<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

/**
 * Vue de lecture de l'écran « Compte ».
 *
 * Ne transporte aucun secret : ni le secret TOTP, ni les codes de secours.
 * Un écran n'a besoin que de savoir combien il en reste.
 */
final readonly class AccountSettings
{
    /** @param list<string> $linkedProviders */
    public function __construct(
        public string $email,
        public bool $twoFactorEnabled,
        public int $remainingBackupCodes,
        public array $linkedProviders,
    ) {
    }

    public function hasLinked(string $provider): bool
    {
        return \in_array($provider, $this->linkedProviders, true);
    }

    public function backupCodesRunningLow(): bool
    {
        return $this->twoFactorEnabled && $this->remainingBackupCodes <= 1;
    }
}
