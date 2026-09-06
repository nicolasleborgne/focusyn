<?php

declare(strict_types=1);

namespace App\Inbox\Infrastructure\Persistence\Doctrine;

use App\Inbox\Domain\Model\Capture;
use App\Inbox\Domain\Model\CaptureId;
use App\Inbox\Domain\Repository\CaptureRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCaptureRepository implements CaptureRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Capture $capture): void
    {
        $this->entityManager->persist($capture);
        $this->entityManager->flush();
    }

    public function remove(Capture $capture): void
    {
        $this->entityManager->remove($capture);
        $this->entityManager->flush();
    }

    public function ofId(CaptureId $id): ?Capture
    {
        // Requête DQL et non `find()` : ce dernier court-circuite les filtres
        // quand il touche le cache d'identité.
        return $this->entityManager
            ->createQuery('SELECT c FROM '.Capture::class.' c WHERE c.id = :id')
            ->setParameter('id', $id->toString())
            ->getOneOrNullResult();
    }

    public function pending(): array
    {
        /** @var list<Capture> $captures */
        $captures = $this->entityManager
            ->createQuery('SELECT c FROM '.Capture::class.' c ORDER BY c.capturedAt DESC')
            ->getResult();

        return $captures;
    }

    public function count(): int
    {
        return (int) $this->entityManager
            ->createQuery('SELECT COUNT(c.id) FROM '.Capture::class.' c')
            ->getSingleScalarResult();
    }
}
