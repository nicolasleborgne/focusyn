<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\InvitedEmail;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class InvitedEmailType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(180)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?InvitedEmail
    {
        return match (true) {
            null === $value => null,
            $value instanceof InvitedEmail => $value,
            \is_string($value) => InvitedEmail::fromString($value),
            default => throw InvalidType::new($value, InvitedEmail::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof InvitedEmail => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', InvitedEmail::class]),
        };
    }
}
