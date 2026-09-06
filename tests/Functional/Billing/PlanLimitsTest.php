<?php

declare(strict_types=1);

namespace App\Tests\Functional\Billing;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\Invitation;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\Membership;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Repository\InvitationRepository;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Domain\TenantId;
use App\Tests\Factory\Notebook\NoteFactory;
use App\Tests\Functional\LogsIn;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ce que le palier gratuit ferme, et ce qu'il ne ferme jamais.
 *
 * Chaque organisation naît en essai : ces tests le font expirer d'abord, sans
 * quoi ils ne prouveraient rien.
 */
final class PlanLimitsTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use LogsIn;
    use ResetDatabase;

    public function testANewOrganizationOpensOnATrial(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/abonnement');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Essai en cours', $crawler->text());
        self::assertStringContainsString('Personnel', $crawler->filter('.fx-card__title')->first()->text());
    }

    public function testOnceTheTrialIsOverTheFreePlanApplies(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->endTrial();

        $crawler = $client->request('GET', '/abonnement');

        self::assertStringContainsString('Gratuit', $crawler->filter('.fx-card__title')->first()->text());
        self::assertStringContainsString('notes sur 50', $crawler->text());
    }

    public function testWritingStopsAtTheAllowanceButNothingIsLost(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->fillNotebook(50);
        $this->endTrial();

        $client->request(
            'POST',
            '/notes/nouvelle',
            ['_token' => $this->token($client, '/bibliotheque', '/notes/nouvelle')],
            server: ['HTTP_REFERER' => '/bibliotheque'],
        );
        $crawler = $client->followRedirect();

        self::assertStringContainsString('s\'arrête à cinquante notes', $crawler->filter('.fx-auth__error')->text());

        // Rien n'est perdu : la bibliothèque reste lisible, et l'export aussi.
        self::assertCount(50, $client->request('GET', '/bibliotheque')->filter('.fx-note-row'));
        $client->request('GET', '/reglages/donnees/export.json');
        self::assertResponseIsSuccessful();
    }

    public function testTheAssistantIsClosedOnTheFreePlan(): void
    {
        $client = self::createClient();
        $account = $this->logIn($client);
        $noteId = $this->writeNote($client, 'Le sommeil comme sujet');
        // Le consentement passe avant les droits : sans lui, c'est lui qui
        // refuserait, et le test ne prouverait rien du palier.
        $this->consentToAssistant($client);
        $this->endTrial();

        $rendered = $this->createLiveComponent('AssistantPanel', ['noteId' => $noteId], $client)
            ->actingAs($account)
            ->call('run', ['action' => 'summarise'])
            ->render()
            ->toString();

        self::assertStringContainsString('demande un abonnement', $rendered);
    }

    public function testOpeningATeamIsClosedOnTheFreePlan(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->endTrial();

        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('form[action="/equipe/nouvelle"]')->form(['name' => 'Le studio']));
        $crawler = $client->followRedirect();

        self::assertStringContainsString('demande un abonnement', $crawler->filter('.fx-auth__error')->text());
        self::assertCount(1, $this->organizationsOfDemo());
    }

    public function testATeamStopsAtTheSeatsItPaysForButKeepsItsMembers(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openTeam($client, 'Le studio');

        // Le propriétaire occupe déjà une place : quatre invitations
        // remplissent les cinq de l'essai.
        foreach (['ana', 'bo', 'cyd', 'dov'] as $name) {
            $this->invite($client, $name.'@exemple.fr');
        }

        $crawler = $this->invite($client, 'la-personne-de-trop@exemple.fr');

        self::assertStringContainsString('au complet', $crawler->filter('.fx-auth__error')->text());

        // L'invitation de trop n'est pas partie, et celles qui tenaient dans
        // les places restent valables : un plafond arrête, il n'annule pas.
        self::assertCount(4, $this->invitationsOfTeam());
    }

    public function testAnInvitationSentInTimeStillDoesNotEnterOnceThePlacesAreGone(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->openTeam($client, 'Le studio');
        $this->invite($client, 'camille@focusyn.fr');

        // Entre l'envoi et l'acceptation, l'essai s'achève : il ne reste
        // qu'une place, et le propriétaire l'occupe.
        $this->endTrial();

        $token = $this->invitationsOfTeam()[0]->token()->toString();
        $this->signOut($client);
        $this->logIn($client, 'camille@focusyn.fr');
        $client->request('GET', '/invitations/'.$token);
        $client->followRedirect();

        // Elle n'entre pas, et l'invitation n'est pas consommée pour autant :
        // elle vaudra encore le jour où une place se paie.
        self::assertCount(1, $this->membersOfTeam());
        self::assertFalse($this->invitationsOfTeam()[0]->isAccepted());
    }

    private function consentToAssistant(KernelBrowser $client): void
    {
        $client->request('POST', '/reglages/donnees', [
            'consent' => 'assistant',
            'granted' => '1',
            '_token' => $this->token($client, '/reglages', '/reglages/donnees'),
        ]);
        $client->followRedirect();
    }

    private function endTrial(): void
    {
        $subscriptions = self::getContainer()->get(SubscriptionRepository::class);
        self::assertInstanceOf(SubscriptionRepository::class, $subscriptions);

        foreach ($this->organizationsOfDemo() as $organization) {
            $subscription = $subscriptions->ofOrganization(TenantId::fromString($organization->id()->toString()));
            self::assertNotNull($subscription);
            // On force l'expiration : l'essai est ouvert par l'événement de
            // création, on ne peut pas l'éviter, seulement le dépasser.
            $subscription->activate(Plan::Free, new DateTimeImmutable('2020-01-01'), 1);
            $subscriptions->save($subscription);
        }
    }

    private function fillNotebook(int $count): void
    {
        NoteFactory::createMany($count, [
            'tenantId' => TenantId::fromString($this->organizationsOfDemo()[0]->id()->toString()),
        ]);
    }

    /** @return list<Organization> */
    private function organizationsOfDemo(): array
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString('nicolas@focusyn.fr'));
        self::assertNotNull($user);

        $organizations = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $organizations);

        return $organizations->ofMember(MemberId::fromString($user->id()->toString()));
    }

    private function openTeam(KernelBrowser $client, string $name): void
    {
        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('form[action="/equipe/nouvelle"]')->form(['name' => $name]));
        $client->followRedirect();
    }

    private function invite(KernelBrowser $client, string $email): Crawler
    {
        $client->request(
            'POST',
            '/equipe/inviter',
            [
                '_token' => $this->token($client, '/equipe', '/equipe/inviter'),
                'email' => $email,
                'role' => 'member',
            ],
            server: ['HTTP_REFERER' => '/equipe'],
        );

        return $client->followRedirect();
    }

    /** @return list<Membership> */
    private function membersOfTeam(): array
    {
        return $this->team()->memberships();
    }

    /** @return list<Invitation> */
    private function invitationsOfTeam(): array
    {
        $invitations = self::getContainer()->get(InvitationRepository::class);
        self::assertInstanceOf(InvitationRepository::class, $invitations);

        return $invitations->ofOrganization($this->team()->id());
    }

    private function team(): Organization
    {
        $team = array_values(array_filter(
            $this->organizationsOfDemo(),
            static fn (Organization $organization): bool => !$organization->isPersonal(),
        ));
        self::assertCount(1, $team);

        return $team[0];
    }

    private function token(KernelBrowser $client, string $page, string $action): string
    {
        return (string) $client->request('GET', $page)
            ->filter('form[action="'.$action.'"] input[name="_token"]')
            ->first()
            ->attr('value');
    }
}
