<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Command\SignInWithOAuth\SignInWithOAuth;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\OAuthProvider;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Infrastructure\Security\OAuth\OAuthProfileReader;
use App\Shared\Application\Command\CommandBus;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Token\AccessToken;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Retour d'un fournisseur externe.
 *
 * L'authentificateur ne décide de rien : il lit le profil, confie la décision
 * au cas d'usage `SignInWithOAuth`, puis ouvre la session pour le compte que
 * celui-ci désigne.
 */
final class OAuthAuthenticator extends OAuth2Authenticator
{
    /** @param iterable<OAuthProfileReader> $readers */
    public function __construct(
        private readonly ClientRegistry $clients,
        private readonly CommandBus $commands,
        private readonly UserRepository $users,
        private readonly UrlGeneratorInterface $urls,
        #[AutowireIterator('app.oauth_profile_reader')]
        private readonly iterable $readers,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'oauth_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $provider = OAuthProvider::from((string) $request->attributes->get('provider'));
        $client = $this->clients->getClient($provider->value);

        $token = $this->fetchAccessToken($client);
        \assert($token instanceof AccessToken);

        $profile = $this->readerFor($provider)->read($client, $token);

        $this->commands->dispatch(new SignInWithOAuth(
            $provider->value,
            $profile->externalId,
            $profile->email,
            $profile->emailVerified,
        ));

        return new SelfValidatingPassport(
            new UserBadge($profile->email, function (string $identifier): ?SecurityUser {
                $user = $this->users->ofEmail(EmailAddress::fromString($identifier));

                return null === $user ? null : SecurityUser::fromDomain($user);
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urls->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();

        if ($session instanceof Session) {
            // Le message porté par l'exception vient du cas d'usage : il
            // explique pourquoi le rattachement a été refusé.
            $session->getFlashBag()->add(
                'oauth_error',
                $exception->getPrevious()?->getMessage() ?? $exception->getMessageKey(),
            );
        }

        return new RedirectResponse($this->urls->generate('login'));
    }

    private function readerFor(OAuthProvider $provider): OAuthProfileReader
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($provider)) {
                return $reader;
            }
        }

        throw new LogicException(\sprintf('Aucun lecteur de profil pour %s.', $provider->value));
    }
}
