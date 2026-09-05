<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Model\EmailAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class EmailAddressType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(180)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EmailAddress
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof EmailAddress) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, EmailAddress::class, ['null', 'string']);
        }

        return EmailAddress::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof EmailAddress => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', EmailAddress::class]),
        };
    }
}
