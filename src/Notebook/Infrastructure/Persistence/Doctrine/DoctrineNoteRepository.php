<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Persistence\Doctrine;

use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\NoteObsession;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Shared\Application\Event\DomainEventBus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNoteRepository implements NoteRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DomainEventBus $events,
    ) {
    }

    public function save(Note $note): void
    {
        $this->entityManager->persist($note);
        $this->entityManager->flush();

        $this->events->publish(...$note->releaseEvents());
    }

    public function remove(Note $note): void
    {
        $this->entityManager->remove($note);
        $this->entityManager->flush();
    }

    public function ofId(NoteId $id): ?Note
    {
        // `find()` court-circuite les filtres lorsqu'il touche le cache
        // d'identité : on passe par une requête pour que le cloisonnement
        // s'applique dans tous les cas.
        return $this->entityManager
            ->createQuery('SELECT n FROM '.Note::class.' n WHERE n.id = :id')
            ->setParameter('id', $id->toString())
            ->getOneOrNullResult();
    }

    public function mostRecent(int $limit = 50): array
    {
        /** @var list<Note> $notes */
        $notes = $this->entityManager
            ->createQuery('SELECT n FROM '.Note::class.' n ORDER BY n.updatedAt DESC')
            ->setMaxResults($limit)
            ->getResult();

        return $notes;
    }

    public function taggedWith(ObsessionName $obsession, int $limit = 50): array
    {
        /** @var list<Note> $notes */
        $notes = $this->entityManager
            ->createQuery(
                'SELECT n FROM '.Note::class.' n'
                .' JOIN '.NoteObsession::class.' o WITH o.note = n'
                .' WHERE o.slug = :slug'
                .' ORDER BY n.updatedAt DESC',
            )
            ->setParameter('slug', $obsession->slug())
            ->setMaxResults($limit)
            ->getResult();

        return $notes;
    }

    public function matching(string $query, int $limit = 50): array
    {
        $needle = trim($query);

        if ('' === $needle) {
            return [];
        }

        /** @var list<Note> $notes */
        $notes = $this->entityManager
            ->createQuery(
                'SELECT n FROM '.Note::class.' n'
                .' WHERE LOWER(n.title) LIKE :needle OR LOWER(n.body) LIKE :needle'
                .' ORDER BY n.updatedAt DESC',
            )
            ->setParameter('needle', '%'.mb_strtolower($needle).'%')
            ->setMaxResults($limit)
            ->getResult();

        return $notes;
    }

    public function count(): int
    {
        return (int) $this->entityManager
            ->createQuery('SELECT COUNT(n.id) FROM '.Note::class.' n')
            ->getSingleScalarResult();
    }

    public function obsessionCounts(): array
    {
        /** @var list<array{name: string, slug: string, count: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery(
                'SELECT o.name AS name, o.slug AS slug, COUNT(o.slug) AS count'
                .' FROM '.NoteObsession::class.' o'
                .' JOIN '.Note::class.' n WITH o.note = n'
                .' GROUP BY o.slug, o.name'
                .' ORDER BY count DESC, o.name ASC',
            )
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'name' => $row['name'],
                'slug' => $row['slug'],
                'count' => (int) $row['count'],
            ],
            $rows,
        );
    }
}
