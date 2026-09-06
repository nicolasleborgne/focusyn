<?php

declare(strict_types=1);

namespace App\Tests\Functional\Organization;

use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\InvitedEmail;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\InvitationRepository;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Tests\Functional\LogsIn;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le parcours de celui qui reçoit une invitation sans avoir de compte.
 *
 * Le lien doit lui apprendre de quoi il retourne, puis le mener jusqu'à
 * l'équipe sans qu'il ait à recliquer quoi que ce soit.
 */
final class InvitationWithoutAccountTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testAVisitorSeesWhatTheInvitationIsAboutRatherThanALoginWall(): void
    {
        $client = self::createClient();
        $token = $this->invitationFor($client, 'camille@focusyn.fr');
        $this->signOut($client);

        $crawler = $client->request('GET', '/invitations/'.$token);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Le studio', $crawler->text());
        self::assertStringContainsString('camille@focusyn.fr', $crawler->text());
    }

    public function testTheRegistrationLinkCarriesTheInvitedAddress(): void
    {
        $client = self::createClient();
        $token = $this->invitationFor($client, 'camille@focusyn.fr');
        $this->signOut($client);

        $crawler = $client->request('GET', '/invitations/'.$token);
        $href = (string) $crawler->filter('a.fx-button--primary')->attr('href');

        $crawler = $client->request('GET', $href);

        // Pré-remplie : sans cela, une adresse saisie de travers se ferait
        // refuser l'invitation sans qu'on comprenne pourquoi.
        self::assertSame('camille@focusyn.fr', $crawler->filter('input[type="email"]')->attr('value'));
    }

    public function testRegisteringFromTheLinkJoinsTheTeamWithoutClickingAgain(): void
    {
        $client = self::createClient();
        $this->invitationFor($client, 'camille@focusyn.fr');
        $this->signOut($client);

        $token = $this->tokenFor('camille@focusyn.fr');
        $client->request('GET', '/invitations/'.$token);

        $client->submit($this->registrationForm($client));
        $client->followRedirect();

        self::assertCount(2, $this->membersOfStudio());

        // Et l'on y travaille : c'est l'espace courant.
        self::assertStringContainsString('Le studio', $client->request('GET', '/equipe')->text());
    }

    public function testAnInvitationIsNotReplayedAtTheNextSignIn(): void
    {
        $client = self::createClient();
        $this->invitationFor($client, 'camille@focusyn.fr');
        $this->signOut($client);

        $token = $this->tokenFor('camille@focusyn.fr');
        $client->request('GET', '/invitations/'.$token);

        $client->submit($this->registrationForm($client));
        $client->followRedirect();
        $this->signOut($client);

        // Le jeton a été consommé : se reconnecter ne rejoue rien.
        $this->logIn($client, 'camille@focusyn.fr');
        $client->request('GET', '/');

        self::assertCount(2, $this->membersOfStudio());
    }

    public function testAnExpiredLinkSaysSoRatherThanAskingToSignIn(): void
    {
        $client = self::createClient();

        $client->request('GET', '/invitations/'.str_repeat('a', 32));

        self::assertResponseStatusCodeSame(410);
        self::assertStringContainsString('ne vaut plus', $client->getCrawler()->text());
    }

    /**
     * Le formulaire d'inscription, déjà rempli de l'adresse invitée.
     */
    private function registrationForm(KernelBrowser $client): Form
    {
        $form = $client->request('GET', '/inscription')->selectButton('Créer le compte')->form();
        $form['registration_form[email]'] = 'camille@focusyn.fr';
        $form['registration_form[plainPassword]'] = 'une phrase de passe tenable';

        return $form;
    }

    private function invitationFor(KernelBrowser $client, string $email): string
    {
        $this->logIn($client);

        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('form[action="/equipe/nouvelle"]')->form(['name' => 'Le studio']));
        $client->followRedirect();

        $token = (string) $client->request('GET', '/equipe')
            ->filter('form[action="/equipe/inviter"] input[name="_token"]')
            ->first()
            ->attr('value');
        $client->request('POST', '/equipe/inviter', ['_token' => $token, 'email' => $email, 'role' => 'member']);
        $client->followRedirect();

        return $this->tokenFor($email);
    }

    private function tokenFor(string $email): string
    {
        $invitations = self::getContainer()->get(InvitationRepository::class);
        self::assertInstanceOf(InvitationRepository::class, $invitations);

        foreach ($this->organizations()->ofMember(MemberId::fromString($this->ownerId())) as $organization) {
            $pending = $invitations->pendingFor($organization->id(), InvitedEmail::fromString($email));

            if (null !== $pending) {
                return $pending->token()->toString();
            }
        }

        self::fail('Aucune invitation en cours pour '.$email);
    }

    /** @return list<object> */
    private function membersOfStudio(): array
    {
        foreach ($this->organizations()->ofMember(MemberId::fromString($this->ownerId())) as $organization) {
            if ('Le studio' === $organization->name()) {
                return $organization->memberships();
            }
        }

        self::fail('L\'équipe « Le studio » est introuvable.');
    }

    private function ownerId(): string
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString('nicolas@focusyn.fr'));
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
