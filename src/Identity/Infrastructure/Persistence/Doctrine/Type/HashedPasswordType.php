<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Model\HashedPassword;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class HashedPasswordType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(255)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?HashedPassword
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof HashedPassword) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, HashedPassword::class, ['null', 'string']);
        }

        return HashedPassword::fromHash($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof HashedPassword => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', HashedPassword::class]),
        };
    }
}
