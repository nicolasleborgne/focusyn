<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine\Type;

use App\Reminder\Domain\Model\ReminderSubject;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ReminderSubjectType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(64)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ReminderSubject
    {
        return match (true) {
            null === $value => null,
            $value instanceof ReminderSubject => $value,
            \is_string($value) => ReminderSubject::fromString($value),
            default => throw InvalidType::new($value, ReminderSubject::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof ReminderSubject => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', ReminderSubject::class]),
        };
    }
}
