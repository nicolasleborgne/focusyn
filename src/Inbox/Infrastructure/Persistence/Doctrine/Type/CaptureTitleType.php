<?php

declare(strict_types=1);

namespace App\Inbox\Infrastructure\Persistence\Doctrine\Type;

use App\Inbox\Domain\Model\CaptureTitle;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class CaptureTitleType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(70)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CaptureTitle
    {
        return match (true) {
            null === $value => null,
            $value instanceof CaptureTitle => $value,
            \is_string($value) => CaptureTitle::fromText($value),
            default => throw InvalidType::new($value, CaptureTitle::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof CaptureTitle => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', CaptureTitle::class]),
        };
    }
}
