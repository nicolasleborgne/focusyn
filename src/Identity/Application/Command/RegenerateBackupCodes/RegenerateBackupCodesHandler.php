<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RegenerateBackupCodes;

use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Application\Port\BackupCodeGenerator;
use App\Identity\Application\Port\BackupCodeHasher;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegenerateBackupCodesHandler
{
    public function __construct(
        private UserRepository $users,
        private BackupCodeGenerator $codes,
        private BackupCodeHasher $hasher,
    ) {
    }

    /** @return list<string> */
    public function __invoke(RegenerateBackupCodes $command): array
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        $plainCodes = $this->codes->generate();

        // Régénérer invalide les anciens : c'est le geste à faire quand on
        // soupçonne qu'ils ont été vus.
        $user->replaceBackupCodes(array_map($this->hasher->hash(...), $plainCodes));
        $this->users->save($user);

        return $plainCodes;
    }
}
