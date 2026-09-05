<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\EnableTwoFactor;

use App\Identity\Application\Exception\InvalidTotpCode;
use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Application\Port\BackupCodeGenerator;
use App\Identity\Application\Port\BackupCodeHasher;
use App\Identity\Application\Port\TotpProvisioner;
use App\Identity\Domain\Model\TotpSecret;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class EnableTwoFactorHandler
{
    public function __construct(
        private UserRepository $users,
        private TotpProvisioner $totp,
        private BackupCodeGenerator $codes,
        private BackupCodeHasher $hasher,
    ) {
    }

    /**
     * @return list<string> les codes de secours en clair, à montrer une seule fois
     */
    public function __invoke(EnableTwoFactor $command): array
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        $secret = TotpSecret::fromString($command->pendingSecret);

        if (!$this->totp->verify($user->email(), $secret, $command->code)) {
            throw InvalidTotpCode::create();
        }

        $plainCodes = $this->codes->generate();

        $user->enableTwoFactor(
            $secret,
            array_map($this->hasher->hash(...), $plainCodes),
        );
        $this->users->save($user);

        return $plainCodes;
    }
}
