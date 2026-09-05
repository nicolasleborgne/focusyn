<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\Membership;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Event\DomainEventBus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineOrganizationRepository implements OrganizationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DomainEventBus $events,
    ) {
    }

    public function save(Organization $organization): void
    {
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        $this->events->publish(...$organization->releaseEvents());
    }

    public function ofId(OrganizationId $id): ?Organization
    {
        return $this->entityManager->find(Organization::class, $id);
    }

    public function ofSlug(string $slug): ?Organization
    {
        return $this->entityManager
            ->createQuery('SELECT o FROM '.Organization::class.' o WHERE o.slug = :slug')
            ->setParameter('slug', $slug)
            ->getOneOrNullResult();
    }

    public function ofMember(MemberId $member): array
    {
        /** @var list<Organization> $organizations */
        $organizations = $this->entityManager
            ->createQuery(
                'SELECT o FROM '.Organization::class.' o'
                .' JOIN '.Membership::class.' m WITH m.organization = o'
                .' WHERE m.memberId = :member'
                .' ORDER BY o.createdAt ASC',
            )
            ->setParameter('member', $member->toString())
            ->getResult();

        return $organizations;
    }

    public function slugIsTaken(string $slug): bool
    {
        $count = $this->entityManager
            ->createQuery('SELECT COUNT(o.id) FROM '.Organization::class.' o WHERE o.slug = :slug')
            ->setParameter('slug', $slug)
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
