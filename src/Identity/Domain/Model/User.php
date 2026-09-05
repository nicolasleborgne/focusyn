<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Domain\Event\UserWasRegistered;
use App\Shared\Domain\AggregateRoot;
use DateTimeImmutable;

/**
 * Un compte Focusyn.
 *
 * L'agrégat ne connaît ni Doctrine ni Symfony : l'adaptateur de sécurité et le
 * mapping XML vivent dans l'infrastructure du contexte.
 */
final class User extends AggregateRoot
{
    private function __construct(
        private readonly UserId $id,
        private EmailAddress $email,
        private HashedPassword $password,
        private readonly DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        UserId $id,
        EmailAddress $email,
        HashedPassword $password,
        DateTimeImmutable $registeredAt,
    ): self {
        $user = new self($id, $email, $password, $registeredAt);
        $user->recordThat(new UserWasRegistered($id->toString(), $email->toString(), $registeredAt));

        return $user;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function changePassword(HashedPassword $password): void
    {
        $this->password = $password;
    }

    public function changeEmail(EmailAddress $email): void
    {
        if ($this->email->equals($email)) {
            return;
        }

        $this->email = $email;
    }
}
