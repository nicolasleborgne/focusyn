<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine;

use App\Reminder\Domain\Model\PushEndpoint;
use App\Reminder\Domain\Model\PushSubscription;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePushSubscriptionRepository implements PushSubscriptionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(PushSubscription $subscription): void
    {
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    public function remove(PushSubscription $subscription): void
    {
        $this->entityManager->remove($subscription);
        $this->entityManager->flush();
    }

    public function ofEndpoint(PushEndpoint $endpoint): ?PushSubscription
    {
        return $this->entityManager
            ->createQuery('SELECT s FROM '.PushSubscription::class.' s WHERE s.endpoint = :endpoint')
            ->setParameter('endpoint', $endpoint->toString())
            ->getOneOrNullResult();
    }

    public function ofSubscriber(RecipientId $subscriber): array
    {
        /** @var list<PushSubscription> $subscriptions */
        $subscriptions = $this->entityManager
            ->createQuery('SELECT s FROM '.PushSubscription::class.' s WHERE s.subscriberId = :subscriber ORDER BY s.createdAt ASC')
            ->setParameter('subscriber', $subscriber->toString())
            ->getResult();

        return $subscriptions;
    }
}
