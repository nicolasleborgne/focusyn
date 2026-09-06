<?php

declare(strict_types=1);

namespace App\Tests\Functional\Billing;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Model\Subscription;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\LogsIn;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ce que le prestataire nous raconte, et ce qu'on en fait.
 *
 * Rien ne sort de la machine : la signature est calculée ici, avec le même
 * secret que celui configuré en test. C'est elle qui authentifie la requête —
 * Stripe n'a pas de session.
 */
final class StripeWebhookTest extends WebTestCase
{
    use Factories;
    use LogsIn;
    use ResetDatabase;

    private const string SECRET = 'whsec_secret_de_test';

    public function testAnUnsignedCallIsRefused(): void
    {
        $client = self::createClient();

        $client->request('POST', '/webhooks/stripe', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(400);
    }

    public function testASignatureFromAnotherSecretIsRefused(): void
    {
        $client = self::createClient();
        $payload = json_encode(['type' => 'invoice.paid', 'data' => ['object' => []]], \JSON_THROW_ON_ERROR);

        $this->post($client, $payload, $this->signature($payload, 'whsec_autre_chose'));

        self::assertResponseStatusCodeSame(400);
    }

    public function testASubscriptionEventActivatesThePlanAndItsPeriod(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $organization = $this->organizationId();

        $this->send($client, 'customer.subscription.updated', [
            'id' => 'sub_123',
            'customer' => 'cus_123',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'metadata' => ['organization' => $organization],
            'current_period_end' => (new DateTimeImmutable('+30 days'))->getTimestamp(),
            'items' => ['data' => [['quantity' => 3, 'price' => ['metadata' => ['plan' => 'team']]]]],
        ]);

        self::assertResponseIsSuccessful();

        $subscription = $this->subscriptionOf($organization);
        self::assertSame(Plan::Team, $subscription->entitledPlan(new DateTimeImmutable()));
        self::assertSame(3, $subscription->seats());
        self::assertSame('cus_123', $subscription->customerReference());
    }

    public function testAFailedPaymentFallsBackToTheFreePlanWithoutLosingAnything(): void
    {
        $client = self::createClient();
        $this->logIn($client);
        $organization = $this->organizationId();

        $this->send($client, 'customer.subscription.updated', [
            'id' => 'sub_123', 'customer' => 'cus_123', 'status' => 'active', 'cancel_at_period_end' => false,
            'metadata' => ['organization' => $organization],
            'current_period_end' => (new DateTimeImmutable('+30 days'))->getTimestamp(),
            'items' => ['data' => [['quantity' => 1, 'price' => ['metadata' => ['plan' => 'personal']]]]],
        ]);

        $this->send($client, 'invoice.payment_failed', [
            'customer' => 'cus_123',
            'subscription' => 'sub_123',
            'metadata' => ['organization' => $organization],
        ]);

        $subscription = $this->subscriptionOf($organization);
        self::assertSame(Plan::Free, $subscription->entitledPlan(new DateTimeImmutable()));
        self::assertTrue($subscription->needsAttention());

        // Rien n'est perdu : la bibliothèque répond toujours.
        self::assertResponseIsSuccessful();
        $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
    }

    public function testAnEventForAnUnknownSubscriptionIsAcceptedRatherThanRetriedForever(): void
    {
        $client = self::createClient();

        $this->send($client, 'invoice.paid', [
            'customer' => 'cus_inconnu',
            'subscription' => 'sub_inconnu',
        ]);

        // Répondre en erreur ferait réessayer Stripe indéfiniment pour un
        // événement qui ne nous concerne pas.
        self::assertResponseIsSuccessful();
    }

    public function testAnEventTypeWeIgnoreIsStillAccepted(): void
    {
        $client = self::createClient();

        $this->send($client, 'customer.updated', ['id' => 'cus_123']);

        self::assertResponseIsSuccessful();
    }

    /** @param array<string, mixed> $object */
    private function send(KernelBrowser $client, string $type, array $object): void
    {
        $payload = json_encode([
            'id' => 'evt_'.bin2hex(random_bytes(6)),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ], \JSON_THROW_ON_ERROR);

        $this->post($client, $payload, $this->signature($payload, self::SECRET));
    }

    private function post(KernelBrowser $client, string $payload, string $signature): void
    {
        $client->request(
            'POST',
            '/webhooks/stripe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
            content: $payload,
        );
    }

    private function signature(string $payload, string $secret): string
    {
        $timestamp = time();

        return \sprintf(
            't=%d,v1=%s',
            $timestamp,
            hash_hmac('sha256', $timestamp.'.'.$payload, $secret),
        );
    }

    private function organizationId(): string
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->ofEmail(EmailAddress::fromString('nicolas@focusyn.fr'));
        self::assertNotNull($user);

        $organizations = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $organizations);
        $found = $organizations->ofMember(MemberId::fromString($user->id()->toString()));
        self::assertNotSame([], $found);

        return $found[0]->id()->toString();
    }

    private function subscriptionOf(string $organizationId): Subscription
    {
        $subscriptions = self::getContainer()->get(SubscriptionRepository::class);
        self::assertInstanceOf(SubscriptionRepository::class, $subscriptions);
        $subscription = $subscriptions->ofOrganization(TenantId::fromString($organizationId));
        self::assertNotNull($subscription);

        return $subscription;
    }
}
