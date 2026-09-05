<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Authentifie un client de test sans passer par le formulaire.
 *
 * Le compte est créé par le vrai cas d'usage, et non fabriqué directement :
 * c'est ce qui lui donne son organisation personnelle. Sans elle, le
 * cloisonnement ne laisserait rien passer et tous les écrans seraient vides —
 * un décor de test qui ne ressemblerait à rien de réel.
 */
trait LogsIn
{
    private function logIn(KernelBrowser $client, string $email = 'nicolas@focusyn.fr'): SecurityUser
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);
        $bus->dispatch(new RegisterUser($email, 'une phrase de passe tenable'));

        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString($email));
        self::assertNotNull($user);

        $account = SecurityUser::fromDomain($user);
        $client->loginUser($account);

        return $account;
    }

    /**
     * Crée une note par l'application elle-même, dans l'organisation du compte
     * connecté.
     */
    private function signOut(KernelBrowser $client): void
    {
        $client->request('POST', '/deconnexion');
    }

    private function writeNote(KernelBrowser $client, string $title, string $body = ''): string
    {
        $crawler = $client->request('GET', '/bibliotheque');
        $client->submit($crawler->filter('form[action="/notes/nouvelle"]')->form());
        $client->followRedirect();

        $noteId = (string) $client->getRequest()->attributes->get('id');

        $client->submit($client->getCrawler()->filter('.fx-note__title-form')->form(['title' => $title]));
        $crawler = $client->followRedirect();

        if ('' !== $body) {
            $token = (string) $crawler->filter('.fx-note__editor')->attr('data-note-editor-token-value');
            $client->request(
                'POST',
                '/notes/'.$noteId.'/corps',
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
                content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR),
            );
        }

        return $noteId;
    }
}
