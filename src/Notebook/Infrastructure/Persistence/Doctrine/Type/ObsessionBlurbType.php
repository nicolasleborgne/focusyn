<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine\Type;

use App\Notebook\Domain\Model\ObsessionBlurb;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class ObsessionBlurbType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(220)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ObsessionBlurb
    {
        return match (true) {
            null === $value => null,
            $value instanceof ObsessionBlurb => $value,
            \is_string($value) => ObsessionBlurb::fromString($value),
            default => throw InvalidType::new($value, ObsessionBlurb::class, ['null', 'string']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof ObsessionBlurb => $value->toString(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', ObsessionBlurb::class]),
        };
    }
}
