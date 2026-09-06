<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine\Type;

use App\Reminder\Domain\Model\PushEndpoint;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class PushEndpointType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(500)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PushEndpoint
    {
        return match (true) {
            null === $value => null,
            $value instanceof PushEndpoint => $value,
            \is_string($value) => PushEndpoint::fromString($value),
            default => throw InvalidType::new($value, PushEndpoint::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof PushEndpoint => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', PushEndpoint::class]),
        };
    }
}
