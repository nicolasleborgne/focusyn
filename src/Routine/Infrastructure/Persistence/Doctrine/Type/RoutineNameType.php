<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Persistence\Doctrine\Type;

use App\Routine\Domain\Model\RoutineName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class RoutineNameType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(80)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RoutineName
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineName => $value,
            \is_string($value) => RoutineName::fromString($value),
            default => throw InvalidType::new($value, RoutineName::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineName => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', RoutineName::class]),
        };
    }
}
