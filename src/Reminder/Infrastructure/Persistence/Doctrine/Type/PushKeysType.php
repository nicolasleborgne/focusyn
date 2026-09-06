<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine\Type;

use App\Reminder\Domain\Model\PushKeys;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Les deux clés dans une seule colonne, séparées par un point.
 *
 * Elles ne se lisent jamais l'une sans l'autre et ne servent jamais de critère
 * de recherche : deux colonnes n'apporteraient qu'une jointure de plus à écrire.
 */
final class PushKeysType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(520)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PushKeys
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PushKeys) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, PushKeys::class, ['null', 'string']);
        }

        [$publicKey, $authToken] = array_pad(explode('.', $value, 2), 2, '');

        return PushKeys::of($publicKey, $authToken);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof PushKeys => $value->publicKey().'.'.$value->authToken(),
            \is_string($value) => $value,
            default => throw InvalidType::new($value, 'string', ['null', 'string', PushKeys::class]),
        };
    }
}
