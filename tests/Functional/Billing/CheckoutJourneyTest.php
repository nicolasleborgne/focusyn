<?php

declare(strict_types=1);

namespace App\Tests\Functional\Billing;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Domain\TenantId;
use App\Tests\Double\Billing\FakePaymentProvider;
use App\Tests\Functional\LogsIn;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Le départ vers le paiement.
 *
 * Rien n'est activé ici : ce qui compte est qu'on parte au bon endroit, avec
 * le bon palier et le bon nombre de places. L'activation, elle, revient du
 * webhook signé — et c'est `StripeWebhookTest` qui s'en occupe.
 */
final class CheckoutJourneyTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    public function testTheScreenOffersBothPaidPlans(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/abonnement');

        self::assertCount(2, $crawler->filter('form[action="/abonnement/payer"]'));
        self::assertStringContainsString('Personnel', $crawler->text());
        self::assertStringContainsString('Équipe', $crawler->text());
    }

    public function testDuringTheTrialBothPlansCanStillBePaidFor(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/abonnement');

        // L'essai prête le personnel sans qu'on l'ait payé : désactiver son
        // bouton interdirait de convertir l'essai en abonnement, et il
        // faudrait attendre qu'il expire pour pouvoir payer.
        self::assertCount(0, $crawler->filter('form[action="/abonnement/payer"] button[disabled]'));

        $this->choose($client, 'personal');

        self::assertResponseRedirects('https://paiement.exemple/checkout/personal');
    }

    public function testAPlanAlreadyPaidForIsNotOfferedTwice(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $this->activate($client, Plan::Personal);

        $crawler = $client->request('GET', '/abonnement');

        // Payé et en cours, cette fois : le bouton se tait.
        self::assertCount(1, $crawler->filter('form[action="/abonnement/payer"] button[disabled]'));
        self::assertStringContainsString('En cours', $crawler->filter('button[disabled]')->text());
    }

    public function testWithoutAProviderNothingIsOfferedRatherThanOfferedInVain(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $client->disableReboot();
        $this->payments()->configured = false;

        $crawler = $client->request('GET', '/abonnement');

        // Un bouton qui ne peut rien faire serait pire que pas de bouton :
        // l'écran le dit, et l'essai puis le gratuit continuent de marcher.
        self::assertCount(0, $crawler->filter('form[action="/abonnement/payer"]'));
        self::assertStringContainsString('pas configuré', $crawler->text());
    }

    public function testChoosingAPlanLeavesForTheProvider(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $client->disableReboot();

        $this->choose($client, 'personal');

        self::assertResponseRedirects('https://paiement.exemple/checkout/personal');
        self::assertSame(Plan::Personal, $this->payments()->lastPlan);
        // L'organisation voyage : c'est elle que le webhook retrouvera.
        self::assertNotNull($this->payments()->lastOrganization);
        self::assertSame('nicolas@focusyn.fr', $this->payments()->lastEmail);
    }

    public function testTheTeamPlanCarriesTheNumberOfSeats(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $client->disableReboot();

        $this->choose($client, 'team');

        // Seul en compte personnel : une place. C'est ce que le prestataire
        // facturera, et ce que le webhook nous renverra.
        self::assertSame(Plan::Team, $this->payments()->lastPlan);
        self::assertSame(1, $this->payments()->lastSeats);
    }

    public function testAPlanThatDoesNotExistSendsNobodyAnywhere(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $client->disableReboot();

        $this->choose($client, 'platine');

        self::assertResponseRedirects('/abonnement');
        self::assertNull($this->payments()->lastPlan);
    }

    public function testAProviderThatDoesNotAnswerIsSaidRatherThanSwallowed(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $client->disableReboot();
        $this->payments()->unavailable = true;

        $this->choose($client, 'personal');
        $crawler = $client->followRedirect();

        self::assertStringContainsString('pas répondu', $crawler->filter('.fx-auth__error')->text());
    }

    public function testComingBackFromThePaymentSaysItIsTakenWithoutClaimingItIsDone(): void
    {
        $client = self::createClient();
        $this->logIn($client);

        $crawler = $client->request('GET', '/abonnement?paye=1');

        // Le palier s'ouvrira au webhook : sans ce mot, l'écran montrerait
        // l'ancien palier sans rien dire, et l'on croirait le paiement perdu.
        self::assertStringContainsString('dès qu', $crawler->filter('.fx-settings__notice')->text());
        // Et l'on n'annonce surtout pas le palier comme acquis.
        self::assertStringContainsString('Personnel', $crawler->filter('.fx-card__title')->first()->text());
    }

    /** Sort de l'essai en activant un palier, comme le ferait le webhook. */
    private function activate(KernelBrowser $client, Plan $plan): void
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString('nicolas@focusyn.fr'));
        self::assertNotNull($user);

        $organizations = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $organizations);
        $owned = $organizations->ofMember(MemberId::fromString($user->id()->toString()));

        $subscriptions = self::getContainer()->get(SubscriptionRepository::class);
        self::assertInstanceOf(SubscriptionRepository::class, $subscriptions);

        $subscription = $subscriptions->ofOrganization(TenantId::fromString($owned[0]->id()->toString()));
        self::assertNotNull($subscription);
        $subscription->activate($plan, new DateTimeImmutable('+1 month'), 1);
        $subscriptions->save($subscription);
    }

    private function choose(KernelBrowser $client, string $plan): void
    {
        $token = (string) $client->request('GET', '/abonnement')
            ->filter('form[action="/abonnement/payer"] input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/abonnement/payer', ['_token' => $token, 'plan' => $plan]);
    }

    private function payments(): FakePaymentProvider
    {
        // Déclaré sous `when@test` : le conteneur que lit l'analyse statique
        // est celui de dev, où il n'existe pas.
        /** @phpstan-ignore symfonyContainer.serviceNotFound */
        $provider = self::getContainer()->get(FakePaymentProvider::class);
        self::assertInstanceOf(FakePaymentProvider::class, $provider);

        return $provider;
    }
}
