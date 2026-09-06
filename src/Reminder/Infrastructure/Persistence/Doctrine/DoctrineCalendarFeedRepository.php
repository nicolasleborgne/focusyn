<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Persistence\Doctrine;

use App\Reminder\Domain\Model\CalendarFeed;
use App\Reminder\Domain\Model\FeedToken;
use App\Reminder\Domain\Repository\CalendarFeedRepository;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCalendarFeedRepository implements CalendarFeedRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CalendarFeed $feed): void
    {
        $this->entityManager->persist($feed);
        $this->entityManager->flush();
    }

    public function ofToken(FeedToken $token): ?CalendarFeed
    {
        return $this->entityManager
            ->createQuery('SELECT f FROM '.CalendarFeed::class.' f WHERE f.token = :token')
            ->setParameter('token', $token->toString())
            ->getOneOrNullResult();
    }

    public function ofOrganization(TenantId $organizationId): ?CalendarFeed
    {
        return $this->entityManager
            ->createQuery('SELECT f FROM '.CalendarFeed::class.' f WHERE f.organizationId = :organization')
            ->setParameter('organization', $organizationId->toString())
            ->getOneOrNullResult();
    }
}
