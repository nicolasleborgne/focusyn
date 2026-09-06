<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\LoginSession;
use App\Identity\Domain\Model\UserId;

interface LoginSessionRepository
{
    public function save(LoginSession $session): void;

    public function remove(LoginSession $session): void;

    public function ofId(string $id): ?LoginSession;

    /** @return list<LoginSession> les plus récemment vues d'abord */
    public function ofUser(UserId $userId): array;
}
