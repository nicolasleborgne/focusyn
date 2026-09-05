<?php

declare(strict_types=1);

namespace App\Tests\Factory\Identity;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\HashedPassword;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Les agrégats ont un constructeur privé et une fabrique statique porteuse de
 * sens (`register`). On confie donc l'instanciation à cette fabrique plutôt
 * qu'à la réflexion : les tests construisent des objets par le même chemin que
 * la production, événements de domaine compris.
 *
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    public function withEmail(string $email): static
    {
        return $this->with(['email' => EmailAddress::fromString($email)]);
    }

    public function withPassword(string $hash): static
    {
        return $this->with(['password' => HashedPassword::fromHash($hash)]);
    }

    protected function defaults(): array
    {
        return [
            'id' => UserId::generate(),
            'email' => EmailAddress::fromString(self::faker()->unique()->safeEmail()),
            'password' => HashedPassword::fromHash('$argon2id$v=19$fabrique'),
            'registeredAt' => DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(
            /** @param array{id: UserId, email: EmailAddress, password: HashedPassword, registeredAt: DateTimeImmutable} $parameters */
            static fn (array $parameters): User => User::register(
                $parameters['id'],
                $parameters['email'],
                $parameters['password'],
                $parameters['registeredAt'],
            ),
        );
    }
}
