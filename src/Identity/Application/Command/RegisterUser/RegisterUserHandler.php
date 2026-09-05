<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RegisterUser;

use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterUser $command): UserId
    {
        $email = EmailAddress::fromString($command->email);

        // Contrôle applicatif doublé d'un index unique en base : ce test seul
        // laisserait passer deux inscriptions simultanées sur la même adresse.
        if ($this->users->emailIsTaken($email)) {
            throw EmailAlreadyRegistered::for($email);
        }

        $user = User::register(
            UserId::generate(),
            $email,
            $this->passwords->hash($command->plainPassword),
            $this->clock->now(),
        );

        $this->users->save($user);

        return $user->id();
    }
}
