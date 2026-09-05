<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\DisplayPreferences;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Display\CurrentDisplay;

final class AccountDisplay implements CurrentDisplay
{
    private ?DisplayPreferences $resolved = null;

    public function __construct(
        private readonly CurrentAccount $account,
        private readonly UserRepository $users,
    ) {
    }

    public function accent(): string
    {
        return $this->preferences()->accent->value;
    }

    public function proseFont(): string
    {
        return $this->preferences()->proseFont->value;
    }

    public function density(): string
    {
        return $this->preferences()->density->value;
    }

    public function markOpacity(): float
    {
        return $this->preferences()->markOpacity->toFloat();
    }

    public function previewPane(): bool
    {
        return $this->preferences()->previewPane;
    }

    /**
     * Résolu une seule fois par requête : le gabarit de base interroge cinq
     * réglages, ce qui ferait cinq lectures pour la même réponse.
     */
    private function preferences(): DisplayPreferences
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        $email = $this->account->emailOrNull();

        if (null === $email) {
            return $this->resolved = DisplayPreferences::default();
        }

        return $this->resolved = $this->users->ofEmail(EmailAddress::fromString($email))?->display()
            ?? DisplayPreferences::default();
    }
}
