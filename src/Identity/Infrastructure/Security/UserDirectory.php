<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Account\AccountDirectory;
use InvalidArgumentException;

/**
 * L'annuaire vu depuis Identity.
 *
 * Un rappel notifié par un worker connaît l'identifiant de son destinataire,
 * jamais son adresse : c'est ici qu'elle est résolue, au dernier moment, pour
 * qu'un changement d'adresse s'applique aux rappels déjà posés.
 */
final readonly class UserDirectory implements AccountDirectory
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function emailOf(string $accountId): ?string
    {
        try {
            $user = $this->users->ofId(UserId::fromString($accountId));
        } catch (InvalidArgumentException) {
            return null;
        }

        return $user?->email()->toString();
    }
}
