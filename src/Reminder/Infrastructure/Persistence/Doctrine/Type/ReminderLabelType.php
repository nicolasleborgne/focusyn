<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine\Type;

use App\Reminder\Domain\Model\ReminderLabel;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ReminderLabelType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(200)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ReminderLabel
    {
        return match (true) {
            null === $value => null,
            $value instanceof ReminderLabel => $value,
            \is_string($value) => ReminderLabel::fromString($value),
            default => throw InvalidType::new($value, ReminderLabel::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof ReminderLabel => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', ReminderLabel::class]),
        };
    }
}
