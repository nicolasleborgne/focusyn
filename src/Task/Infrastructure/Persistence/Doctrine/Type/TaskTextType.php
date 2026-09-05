<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Persistence\Doctrine\Type;

use App\Task\Domain\Model\TaskText;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TaskTextType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(500)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TaskText
    {
        return match (true) {
            null === $value => null,
            $value instanceof TaskText => $value,
            \is_string($value) => TaskText::fromString($value),
            default => throw InvalidType::new($value, TaskText::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof TaskText => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', TaskText::class]),
        };
    }
}
