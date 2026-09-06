<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Model\LoginSession;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\LoginSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLoginSessionRepository implements LoginSessionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(LoginSession $session): void
    {
        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }

    public function remove(LoginSession $session): void
    {
        $this->entityManager->remove($session);
        $this->entityManager->flush();
    }

    public function ofId(string $id): ?LoginSession
    {
        return $this->entityManager
            ->createQuery('SELECT s FROM '.LoginSession::class.' s WHERE s.id = :id')
            ->setParameter('id', $id)
            ->getOneOrNullResult();
    }

    public function ofUser(UserId $userId): array
    {
        /** @var list<LoginSession> $sessions */
        $sessions = $this->entityManager
            ->createQuery('SELECT s FROM '.LoginSession::class.' s WHERE s.userId = :user ORDER BY s.lastSeenAt DESC')
            ->setParameter('user', $userId->toString())
            ->getResult();

        return $sessions;
    }
}
