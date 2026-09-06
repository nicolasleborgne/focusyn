<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\Model\InvitationToken;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class InvitationTokenType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(32)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?InvitationToken
    {
        return match (true) {
            null === $value => null,
            $value instanceof InvitationToken => $value,
            \is_string($value) => InvitationToken::fromString($value),
            default => throw InvalidType::new($value, InvitationToken::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof InvitationToken => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', InvitationToken::class]),
        };
    }
}
