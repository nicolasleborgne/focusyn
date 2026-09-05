<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;

interface UserRepository
{
    public function save(User $user): void;

    public function ofId(UserId $id): ?User;

    public function ofEmail(EmailAddress $email): ?User;

    public function emailIsTaken(EmailAddress $email): bool;
}
