<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\ResetPassword;

use SensitiveParameter;

final readonly class ResetPassword
{
    public function __construct(
        public string $userId,
        #[SensitiveParameter]
        public string $plainPassword,
    ) {
    }
}
