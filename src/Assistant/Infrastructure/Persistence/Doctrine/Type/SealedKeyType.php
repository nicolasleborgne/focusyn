<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Type;

use App\Assistant\Domain\Model\SealedKey;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class SealedKeyType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEXT';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SealedKey
    {
        return match (true) {
            null === $value => null,
            $value instanceof SealedKey => $value,
            \is_string($value) => SealedKey::fromCipher($value),
            default => throw InvalidType::new($value, SealedKey::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof SealedKey => $value->cipher(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', SealedKey::class]),
        };
    }
}
