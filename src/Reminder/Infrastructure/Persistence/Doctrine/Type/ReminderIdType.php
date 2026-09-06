<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine\Type;

use App\Reminder\Domain\Model\ReminderId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ReminderIdType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ReminderId
    {
        return match (true) {
            null === $value => null,
            $value instanceof ReminderId => $value,
            \is_string($value) => ReminderId::fromString($value),
            default => throw InvalidType::new($value, ReminderId::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof ReminderId => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', ReminderId::class]),
        };
    }
}
