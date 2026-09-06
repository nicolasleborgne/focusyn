<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Type;

use App\Assistant\Domain\Model\Provider;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ProviderType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(20)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Provider
    {
        return match (true) {
            null === $value => null,
            $value instanceof Provider => $value,
            \is_string($value) => Provider::from($value),
            default => throw InvalidType::new($value, Provider::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof Provider => $value->value,
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', Provider::class]),
        };
    }
}
