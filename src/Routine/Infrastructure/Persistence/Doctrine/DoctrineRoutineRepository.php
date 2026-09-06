<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Persistence\Doctrine;

use App\Routine\Domain\Model\Routine;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Repository\RoutineRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRoutineRepository implements RoutineRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Routine $routine): void
    {
        $this->entityManager->persist($routine);
        $this->entityManager->flush();
    }

    public function remove(Routine $routine): void
    {
        $this->entityManager->remove($routine);
        $this->entityManager->flush();
    }

    public function ofId(RoutineId $id): ?Routine
    {
        // Requête DQL et non `find()` : ce dernier court-circuite les filtres
        // quand il touche le cache d'identité.
        return $this->entityManager
            ->createQuery('SELECT r FROM '.Routine::class.' r WHERE r.id = :id')
            ->setParameter('id', $id->toString())
            ->getOneOrNullResult();
    }

    public function all(): array
    {
        /** @var list<Routine> $routines */
        $routines = $this->entityManager
            ->createQuery('SELECT r FROM '.Routine::class.' r ORDER BY r.openedAt ASC')
            ->getResult();

        return $routines;
    }
}
