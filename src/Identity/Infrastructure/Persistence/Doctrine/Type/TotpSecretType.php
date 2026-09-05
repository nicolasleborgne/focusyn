<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Model\TotpSecret;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TotpSecretType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(64)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TotpSecret
    {
        return match (true) {
            null === $value => null,
            $value instanceof TotpSecret => $value,
            \is_string($value) => TotpSecret::fromString($value),
            default => throw InvalidType::new($value, TotpSecret::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof TotpSecret => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', TotpSecret::class]),
        };
    }
}
