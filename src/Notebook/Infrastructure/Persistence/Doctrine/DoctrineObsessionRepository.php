<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine;

use App\Notebook\Domain\Model\Obsession;
use App\Notebook\Domain\Repository\ObsessionRepository;
use App\Shared\Application\Event\DomainEventBus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineObsessionRepository implements ObsessionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DomainEventBus $events,
    ) {
    }

    public function save(Obsession $obsession): void
    {
        $this->entityManager->persist($obsession);
        $this->entityManager->flush();

        $this->events->publish(...$obsession->releaseEvents());
    }

    public function remove(Obsession $obsession): void
    {
        $this->entityManager->remove($obsession);
        $this->entityManager->flush();
    }

    public function ofSlug(string $slug): ?Obsession
    {
        return $this->entityManager
            ->createQuery('SELECT o FROM '.Obsession::class.' o WHERE o.slug = :slug')
            ->setParameter('slug', $slug)
            ->getOneOrNullResult();
    }
}
