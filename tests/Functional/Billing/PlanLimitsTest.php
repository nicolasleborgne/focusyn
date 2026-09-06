<?php

declare(strict_types=1);

namespace App\Tests\Functional\Billing;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Domain\TenantId;
use App\Tests\Factory\Notebook\NoteFactory;
use App\Tests\Functional\LogsIn;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

    private function token(KernelBrowser $client, string $page, string $action): string
    {
        return (string) $client->request('GET', $page)
            ->filter('form[action="'.$action.'"] input[name="_token"]')
            ->first()
            ->attr('value');
    }
}
