<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\SignInWithOAuth;

use App\Identity\Application\Exception\UnverifiedOAuthEmail;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\OAuthIdentityId;
use App\Identity\Domain\Model\OAuthProvider;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Connexion par un fournisseur externe, en trois cas :
 *
 *   1. le fournisseur est déjà rattaché → on ouvre la session ;
 *   2. l'adresse correspond à un compte existant → on rattache, à condition
 *      que le fournisseur atteste avoir vérifié cette adresse ;
 *   3. personne ne correspond → on crée le compte.
 *
 * Le cas 2 est le point sensible : rattacher sur une adresse non vérifiée
 * laisserait quiconque déclare « je suis nicolas@… » chez un fournisseur
 * permissif prendre la main sur un compte existant.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class SignInWithOAuthHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SignInWithOAuth $command): UserId
    {
        $provider = OAuthProvider::from($command->provider);

        $alreadyLinked = $this->users->ofOAuthIdentity($provider, $command->externalId);

        if (null !== $alreadyLinked) {
            return $alreadyLinked->id();
        }

        $email = EmailAddress::fromString($command->email);
        $user = $this->users->ofEmail($email);

        if (null !== $user && !$command->emailVerified) {
            throw UnverifiedOAuthEmail::create();
        }

        $user ??= $this->openAccountFor($email);

        $user->linkOAuthIdentity(
            OAuthIdentityId::generate(),
            $provider,
            $command->externalId,
            $this->clock->now(),
        );
        $this->users->save($user);

        return $user->id();
    }

    /**
     * Le compte est créé avec un mot de passe aléatoire que personne ne
     * connaît : la connexion se fera par le fournisseur, ou par la procédure de
     * réinitialisation. Un mot de passe vide ou deviné serait une porte ouverte.
     */
    private function openAccountFor(EmailAddress $email): User
    {
        return User::register(
            UserId::generate(),
            $email,
            $this->passwords->hash(bin2hex(random_bytes(32))),
            $this->clock->now(),
        );
    }
}
