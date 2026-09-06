<?php

declare(strict_types=1);

namespace App\Inbox\Infrastructure\Persistence\Doctrine\Type;

use App\Inbox\Domain\Model\CaptureId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class CaptureIdType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CaptureId
    {
        return match (true) {
            null === $value => null,
            $value instanceof CaptureId => $value,
            \is_string($value) => CaptureId::fromString($value),
            default => throw InvalidType::new($value, CaptureId::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof CaptureId => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', CaptureId::class]),
        };
    }
}
