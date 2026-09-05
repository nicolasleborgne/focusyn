<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine\Type;

use App\Notebook\Domain\Model\ObsessionPoint;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Les points d'une obsession, en JSON.
 *
 * Une table dédiée n'apporterait rien : ils ne sont jamais interrogés
 * séparément, jamais partagés, et leur ordre est celui de la liste. Le type se
 * charge de les rendre au domaine sous forme d'objets valeur plutôt que de
 * chaînes.
 */
final class ObsessionPointsType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSON';
    }

    /** @return list<ObsessionPoint> */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, 'list<ObsessionPoint>', ['null', 'string']);
        }

        /** @var list<string> $decoded */
        $decoded = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);

        return array_map(ObsessionPoint::fromString(...), $decoded);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (null === $value) {
            return '[]';
        }

        if (!\is_array($value)) {
            throw InvalidType::new($value, 'string', ['null', 'array']);
        }

        return json_encode(
            array_map(static fn (mixed $point): string => (string) $point, array_values($value)),
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE,
        );
    }
}
