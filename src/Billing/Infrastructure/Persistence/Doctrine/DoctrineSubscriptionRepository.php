<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Persistence\Doctrine;

use App\Billing\Domain\Model\Subscription;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSubscriptionRepository implements SubscriptionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Subscription $subscription): void
    {
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    public function ofOrganization(TenantId $organizationId): ?Subscription
    {
        return $this->entityManager
            ->createQuery('SELECT s FROM '.Subscription::class.' s WHERE s.organizationId = :organization')
            ->setParameter('organization', $organizationId->toString())
            ->getOneOrNullResult();
    }

    public function ofSubscriptionReference(string $reference): ?Subscription
    {
        return $this->entityManager
            ->createQuery('SELECT s FROM '.Subscription::class.' s WHERE s.subscriptionReference = :reference')
            ->setParameter('reference', $reference)
            ->getOneOrNullResult();
    }

    public function ofCustomerReference(string $reference): ?Subscription
    {
        return $this->entityManager
            ->createQuery('SELECT s FROM '.Subscription::class.' s WHERE s.customerReference = :reference')
            ->setParameter('reference', $reference)
            ->getOneOrNullResult();
    }
}
