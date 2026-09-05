<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Model\DisplayPreferences;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Réglages d'affichage, en JSON.
 *
 * Une colonne par réglage vieillirait mal : chaque nouvelle préférence
 * demanderait une migration, alors qu'aucune n'est jamais interrogée.
 * L'objet valeur se charge de retomber sur les défauts si le contenu stocké
 * ne correspond plus à la version courante.
 */
final class DisplayPreferencesType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSON';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): DisplayPreferences
    {
        if (null === $value || '' === $value) {
            return DisplayPreferences::default();
        }

        if ($value instanceof DisplayPreferences) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, DisplayPreferences::class, ['null', 'string']);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);

        return DisplayPreferences::fromArray($decoded);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (null === $value) {
            return json_encode(DisplayPreferences::default()->toArray(), \JSON_THROW_ON_ERROR);
        }

        if (!$value instanceof DisplayPreferences) {
            throw InvalidType::new($value, 'string', ['null', DisplayPreferences::class]);
        }

        return json_encode($value->toArray(), \JSON_THROW_ON_ERROR);
    }
}
