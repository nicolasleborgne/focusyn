<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Persistence\Doctrine\Type;

use App\Routine\Domain\Model\RoutineId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class RoutineIdType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RoutineId
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineId => $value,
            \is_string($value) => RoutineId::fromString($value),
            default => throw InvalidType::new($value, RoutineId::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineId => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', RoutineId::class]),
        };
    }
}
