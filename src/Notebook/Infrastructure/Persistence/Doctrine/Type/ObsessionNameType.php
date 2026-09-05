<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine\Type;

use App\Notebook\Domain\Model\ObsessionName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ObsessionNameType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(60)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ObsessionName
    {
        return match (true) {
            null === $value => null,
            $value instanceof ObsessionName => $value,
            \is_string($value) => ObsessionName::fromString($value),
            default => throw InvalidType::new($value, ObsessionName::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof ObsessionName => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', ObsessionName::class]),
        };
    }
}
