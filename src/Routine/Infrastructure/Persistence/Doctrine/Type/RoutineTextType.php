<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Persistence\Doctrine\Type;

use App\Routine\Domain\Model\RoutineText;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class RoutineTextType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(240)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RoutineText
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineText => $value,
            \is_string($value) => RoutineText::fromString($value),
            default => throw InvalidType::new($value, RoutineText::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof RoutineText => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', RoutineText::class]),
        };
    }
}
