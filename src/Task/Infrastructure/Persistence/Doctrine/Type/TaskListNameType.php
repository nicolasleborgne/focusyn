<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Persistence\Doctrine\Type;

use App\Task\Domain\Model\TaskListName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TaskListNameType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(120)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TaskListName
    {
        return match (true) {
            null === $value => null,
            $value instanceof TaskListName => $value,
            \is_string($value) => TaskListName::fromString($value),
            default => throw InvalidType::new($value, TaskListName::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof TaskListName => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', TaskListName::class]),
        };
    }
}
