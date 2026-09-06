<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Persistence\Doctrine\Type;

use App\Billing\Domain\Model\SubscriptionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class SubscriptionIdType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SubscriptionId
    {
        return match (true) {
            null === $value => null,
            $value instanceof SubscriptionId => $value,
            \is_string($value) => SubscriptionId::fromString($value),
            default => throw InvalidType::new($value, SubscriptionId::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof SubscriptionId => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', SubscriptionId::class]),
        };
    }
}
