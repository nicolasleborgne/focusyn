<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use SensitiveParameter;

/**
 * Les codes de secours sont stockés hachés, comme des mots de passe : la
 * maquette les affiche une fois, ils ne doivent plus être lisibles ensuite,
 * pas même par un administrateur de la base.
 */
interface BackupCodeHasher
{
    public function hash(#[SensitiveParameter] string $code): string;

    public function verify(string $hash, #[SensitiveParameter] string $code): bool;
}
