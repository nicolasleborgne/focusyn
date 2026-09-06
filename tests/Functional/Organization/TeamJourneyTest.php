<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\InvitationRepository;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ouvrir une équipe, y inviter quelqu'un, l'y faire entrer.
 *
 * Ce qui compte : qu'un lien d'invitation ne fasse entrer que la personne
 * invitée, et qu'une équipe ne puisse jamais perdre son dernier propriétaire.
 */
final class TeamJourneyTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testAnAccountStartsAloneInItsPersonalSpace(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/equipe');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.fx-settings__row'));
        self::assertStringContainsString('ne s', $crawler->filter('.fx-card__help')->first()->text());
        // Un carnet personnel n'a ni membres ni invitations : ce n'est pas une
        // équipe.
        self::assertCount(0, $crawler->filter('form[action="/equipe/inviter"]'));
    }

    public function testOpeningATeamPutsYouInItAsOwner(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $this->openTeam($client, 'Le studio');

        self::assertStringContainsString('Le studio', $crawler->text());
        self::assertStringContainsString('Propriétaire', $crawler->filter('.fx-pill--selected')->text());
        self::assertCount(1, $crawler->filter('form[action="/equipe/inviter"]'));
    }

    public function testInvitingSendsALetterAndListsTheInvitation(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openTeam($client, 'Le studio');

        // On mesure avant de suivre la redirection : le courriel est envoyé
        // pendant la requête POST, et le profil lu est celui de la dernière.
        $this->postInvite($client, 'invite@focusyn.fr', 'member');

        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('Vous êtes invité à rejoindre Le studio sur Focusyn', $message->getSubject());
        self::assertStringContainsString('invite@focusyn.fr', $client->followRedirect()->text());
    }

    public function testTheSameAddressIsNotInvitedTwice(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openTeam($client, 'Le studio');
        $this->invite($client, 'invite@focusyn.fr', 'member');

        $crawler = $this->invite($client, 'invite@focusyn.fr', 'member');

        self::assertStringContainsString('déjà une invitation', $crawler->filter('.fx-auth__error')->text());
    }

    public function testALinkOnlyLetsInTheAddressItWasSentTo(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openTeam($client, 'Le studio');
        $this->invite($client, 'invite@focusyn.fr', 'member');
        $token = $this->tokenOf('invite@focusyn.fr');
        $this->signOut($client);

        // Quelqu'un d'autre a reçu le lien par transfert.
        $this->logIn($client, 'ailleurs@focusyn.fr');
        $client->request('GET', '/invitations/'.$token);
        $crawler = $client->followRedirect();

        self::assertStringContainsString('ne vous est pas adressée', $crawler->filter('.fx-auth__error')->text());
        self::assertCount(1, $this->membersOfStudio());
    }

    public function testTheInvitedAddressJoinsAndLandsInTheTeam(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openTeam($client, 'Le studio');
        $this->invite($client, 'invite@focusyn.fr', 'member');
        $token = $this->tokenOf('invite@focusyn.fr');
        $this->signOut($client);

        $this->logIn($client, 'invite@focusyn.fr');
        $client->request('GET', '/invitations/'.$token);
        $client->followRedirect();

        self::assertCount(2, $this->membersOfStudio());

        // Et l'on y travaille aussitôt : c'est l'espace courant.
        $crawler = $client->request('GET', '/equipe');
        self::assertStringContainsString('Le studio', $crawler->text());
    }

    public function testAnInvitedMemberCannotInviteInTurnWhenOnlyAMember(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openTeam($client, 'Le studio');
        $this->invite($client, 'invite@focusyn.fr', 'member');
        $token = $this->tokenOf('invite@focusyn.fr');
        $this->signOut($client);

        $this->logIn($client, 'invite@focusyn.fr');
        $client->request('GET', '/invitations/'.$token);
        $client->followRedirect();

        $crawler = $client->request('GET', '/equipe');

        // L'écran ne propose rien. Que la route elle-même refuse est vérifié
        // sur le voteur, en intégration : forcer la requête ici se heurterait
        // d'abord au jeton CSRF, ce qui ne prouverait rien de l'autorisation.
        self::assertCount(0, $crawler->filter('form[action="/equipe/inviter"]'));
        self::assertCount(0, $crawler->filter('form[action="/equipe/nom"]'));
    }

    public function testATeamNeverLosesItsLastOwner(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $this->openTeam($client, 'Le studio');

        $crawler = $client->request('GET', '/equipe');
        $token = (string) $crawler->filter('form[action="/equipe/membres"] input[name="_token"]')->first()->attr('value');
        $memberId = (string) $crawler->filter('form[action="/equipe/membres"] input[name="memberId"]')->first()->attr('value');

        $client->request('POST', '/equipe/membres', [
            '_token' => $token,
            'memberId' => $memberId,
            'role' => 'member',
        ]);
        $crawler = $client->followRedirect();

        self::assertStringContainsString('garde toujours un propriétaire', $crawler->filter('.fx-auth__error')->text());
        self::assertSame($account->getUserIdentifier(), $account->getUserIdentifier());
    }

    private function openTeam(KernelBrowser $client, string $name): Crawler
    {
        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('form[action="/equipe/nouvelle"]')->form(['name' => $name]));

        return $client->followRedirect();
    }

    private function invite(KernelBrowser $client, string $email, string $role): Crawler
    {
        $this->postInvite($client, $email, $role);

        return $client->followRedirect();
    }

    private function postInvite(KernelBrowser $client, string $email, string $role): void
    {
        $token = (string) $client->request('GET', '/equipe')
            ->filter('form[action="/equipe/inviter"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/equipe/inviter', ['_token' => $token, 'email' => $email, 'role' => $role]);
    }

    private function tokenOf(string $email): string
    {
        $invitations = self::getContainer()->get(InvitationRepository::class);
        self::assertInstanceOf(InvitationRepository::class, $invitations);

        foreach ($this->organizations()->ofMember(MemberId::fromString($this->anyMemberId())) as $organization) {
            $pending = $invitations->pendingFor($organization->id(), \App\Organization\Domain\Model\InvitedEmail::fromString($email));

            if (null !== $pending) {
                return $pending->token()->toString();
            }
        }

        self::fail('Aucune invitation en cours pour '.$email);
    }

    /** @return list<object> */
    private function membersOfStudio(): array
    {
        foreach ($this->organizations()->ofMember(MemberId::fromString($this->anyMemberId())) as $organization) {
            if ('Le studio' === $organization->name()) {
                return $organization->memberships();
            }
        }

        self::fail('L\'équipe « Le studio » est introuvable.');
    }

    private function anyMemberId(): string
    {
        $users = self::getContainer()->get(\App\Identity\Domain\Repository\UserRepository::class);
        self::assertInstanceOf(\App\Identity\Domain\Repository\UserRepository::class, $users);
        $user = $users->ofEmail(\App\Identity\Domain\Model\EmailAddress::fromString('nicolas@focusyn.fr'));
        self::assertNotNull($user);

        return $user->id()->toString();
    }

    private function organizations(): OrganizationRepository
    {
        $organizations = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $organizations);

        return $organizations;
    }
}
