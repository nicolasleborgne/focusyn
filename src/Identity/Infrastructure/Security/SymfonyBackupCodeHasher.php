<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\BackupCodeHasher;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class SymfonyBackupCodeHasher implements BackupCodeHasher
{
    public function __construct(
        private PasswordHasherFactoryInterface $hashers,
    ) {
    }

    public function hash(#[SensitiveParameter] string $code): string
    {
        return $this->hashers->getPasswordHasher(SecurityUser::class)->hash($code);
    }

    public function verify(string $hash, #[SensitiveParameter] string $code): bool
    {
        return $this->hashers->getPasswordHasher(SecurityUser::class)->verify($hash, $code);
    }
}
