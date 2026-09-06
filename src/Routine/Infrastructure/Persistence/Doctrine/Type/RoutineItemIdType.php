<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Persistence\Doctrine\Type;

use App\Routine\Domain\Model\RoutineItemId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class RoutineItemIdType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RoutineItemId
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineItemId => $value,
            \is_string($value) => RoutineItemId::fromString($value),
            default => throw InvalidType::new($value, RoutineItemId::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineItemId => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', RoutineItemId::class]),
        };
    }
}
