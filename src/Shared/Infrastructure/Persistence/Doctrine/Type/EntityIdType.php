<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\EntityId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Socle des types Doctrine pour les identifiants typés.
 *
 * Stocke un `uuid` natif PostgreSQL : moitié moins d'octets qu'une chaîne de
 * 36 caractères, comparaison plus rapide, et validation par la base.
 *
 * @template T of EntityId
 */
abstract class EntityIdType extends Type
{
    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    /** @return T|null */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EntityId
    {
        if (null === $value) {
            return null;
        }

        $class = $this->idClass();

        if ($value instanceof $class) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, $class, ['null', 'string']);
        }

        return $class::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof EntityId) {
            return $value->toString();
        }

        if (\is_string($value)) {
            return $value;
        }

        throw InvalidType::new($value, 'string', ['null', 'string', EntityId::class]);
    }

    /** @return class-string<T> */
    abstract protected function idClass(): string;
}
