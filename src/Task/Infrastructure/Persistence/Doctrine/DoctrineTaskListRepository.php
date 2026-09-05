<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Persistence\Doctrine;

use App\Shared\Application\Event\DomainEventBus;
use App\Task\Domain\Model\TaskItem;
use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Repository\TaskListRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTaskListRepository implements TaskListRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DomainEventBus $events,
    ) {
    }

    public function save(TaskList $list): void
    {
        $this->entityManager->persist($list);
        $this->entityManager->flush();

        $this->events->publish(...$list->releaseEvents());
    }

    public function remove(TaskList $list): void
    {
        $this->entityManager->remove($list);
        $this->entityManager->flush();
    }

    public function ofId(TaskListId $id): ?TaskList
    {
        // Requête DQL et non `find()` : ce dernier court-circuite les filtres
        // quand il touche le cache d'identité.
        return $this->entityManager
            ->createQuery('SELECT l FROM '.TaskList::class.' l WHERE l.id = :id')
            ->setParameter('id', $id->toString())
            ->getOneOrNullResult();
    }

    public function all(): array
    {
        /** @var list<TaskList> $lists */
        $lists = $this->entityManager
            ->createQuery('SELECT l FROM '.TaskList::class.' l ORDER BY l.openedAt ASC')
            ->getResult();

        return $lists;
    }

    public function openTaskCount(): int
    {
        return (int) $this->entityManager
            ->createQuery(
                'SELECT COUNT(i.id) FROM '.TaskItem::class.' i'
                .' JOIN '.TaskList::class.' l WITH i.list = l'
                .' WHERE i.done = false',
            )
            ->getSingleScalarResult();
    }
}
