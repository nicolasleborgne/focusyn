<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\HashedPassword;
use App\Identity\Domain\Repository\UserRepository;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class DomainUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $email = EmailAddress::fromString($identifier);
        } catch (InvalidArgumentException) {
            throw new UserNotFoundException();
        }

        $user = $this->users->ofEmail($email) ?? throw new UserNotFoundException();

        return SecurityUser::fromDomain($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Compte inattendu : %s.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    /**
     * Réécrit l'empreinte quand l'algorithme évolue, sans demander à
     * l'utilisateur de changer de mot de passe.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface|UserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        $domainUser = $this->users->ofEmail(EmailAddress::fromString($user->getUserIdentifier()));

        if (null === $domainUser) {
            return;
        }

        $domainUser->changePassword(HashedPassword::fromHash($newHashedPassword));
        $this->users->save($domainUser);
    }
}
