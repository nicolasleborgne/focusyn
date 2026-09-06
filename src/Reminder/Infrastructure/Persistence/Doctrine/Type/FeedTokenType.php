<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine\Type;

use App\Reminder\Domain\Model\FeedToken;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class FeedTokenType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(43)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?FeedToken
    {
        return match (true) {
            null === $value => null,
            $value instanceof FeedToken => $value,
            \is_string($value) => FeedToken::fromString($value),
            default => throw InvalidType::new($value, FeedToken::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof FeedToken => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', FeedToken::class]),
        };
    }
}
