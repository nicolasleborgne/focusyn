<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RegisterUser;

use SensitiveParameter;

final readonly class RegisterUser
{
    public function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $plainPassword,
    ) {
    }
}
