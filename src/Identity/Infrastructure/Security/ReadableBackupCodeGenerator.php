<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\BackupCodeGenerator;

/**
 * Codes de secours lisibles à voix haute et recopiables sans ambiguïté.
 *
 * L'alphabet exclut O/0, I/1, S/5, B/8 et Z/2 : ces codes sont souvent notés
 * à la main sur un papier rangé dans un tiroir, et une confusion à la
 * relecture équivaut à une perte d'accès.
 */
final readonly class ReadableBackupCodeGenerator implements BackupCodeGenerator
{
    private const string ALPHABET = 'ACDEFGHJKLMNPQRTUVWXY3479';

    public function generate(int $count = 3): array
    {
        return array_map(
            fn (): string => $this->group().'-'.$this->group(),
            range(1, max(1, $count)),
        );
    }

    private function group(): string
    {
        $length = \strlen(self::ALPHABET) - 1;

        return implode('', array_map(
            static fn (): string => self::ALPHABET[random_int(0, $length)],
            range(1, 5),
        ));
    }
}
