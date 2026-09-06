<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine\Type;

use App\Reminder\Domain\Model\CalendarFeedId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class CalendarFeedIdType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CalendarFeedId
    {
        return match (true) {
            null === $value => null,
            $value instanceof CalendarFeedId => $value,
            \is_string($value) => CalendarFeedId::fromString($value),
            default => throw InvalidType::new($value, CalendarFeedId::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof CalendarFeedId => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', CalendarFeedId::class]),
        };
    }
}
