<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use App\Notebook\Domain\Event\NoteWasWritten;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TenantScoped;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Une note du carnet.
 *
 * Cloisonnée par organisation : c'est le premier agrégat porteur de
 * `TenantScoped`, donc le premier auquel le filtre Doctrine s'applique.
 *
 * Les horodatages ne bougent que si quelque chose a réellement changé. La
 * sauvegarde de l'éditeur est automatique et fréquente ; sans cette précaution,
 * ouvrir une note suffirait à la faire remonter en tête des récentes.
 */
final class Note extends AggregateRoot implements TenantScoped
{
    /** @var Collection<int, NoteObsession> */
    private Collection $obsessions;

    private function __construct(
        private readonly NoteId $id,
        private readonly TenantId $tenantId,
        private readonly AuthorId $authorId,
        private NoteTitle $title,
        private NoteBody $body,
        private readonly DateTimeImmutable $writtenAt,
        private DateTimeImmutable $updatedAt,
    ) {
        $this->obsessions = new ArrayCollection();
    }

    /**
     * @param list<ObsessionName> $obsessions
     */
    public static function write(
        NoteId $id,
        TenantId $tenantId,
        AuthorId $authorId,
        NoteTitle $title,
        NoteBody $body,
        array $obsessions,
        DateTimeImmutable $writtenAt,
    ): self {
        $note = new self($id, $tenantId, $authorId, $title, $body, $writtenAt, $writtenAt);

        foreach ($obsessions as $obsession) {
            $note->attach($obsession);
        }

        $note->recordThat(new NoteWasWritten(
            $id->toString(),
            $tenantId->toString(),
            $authorId->toString(),
            $title->toString(),
            $writtenAt,
        ));

        return $note;
    }

    public function id(): NoteId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function authorId(): AuthorId
    {
        return $this->authorId;
    }

    public function title(): NoteTitle
    {
        return $this->title;
    }

    public function body(): NoteBody
    {
        return $this->body;
    }

    public function writtenAt(): DateTimeImmutable
    {
        return $this->writtenAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<ObsessionName> */
    public function obsessions(): array
    {
        return array_values(array_map(
            static fn (NoteObsession $obsession): ObsessionName => $obsession->name(),
            $this->obsessions->toArray(),
        ));
    }

    public function isTaggedWith(ObsessionName $name): bool
    {
        return null !== $this->find($name);
    }

    public function rename(NoteTitle $title, DateTimeImmutable $at): void
    {
        if ($this->title->equals($title)) {
            return;
        }

        $this->title = $title;
        $this->updatedAt = $at;
    }

    public function rewrite(NoteBody $body, DateTimeImmutable $at): void
    {
        if ($this->body->equals($body)) {
            return;
        }

        $this->body = $body;
        $this->updatedAt = $at;
    }

    public function tagWith(ObsessionName $name, DateTimeImmutable $at): void
    {
        if ($this->isTaggedWith($name)) {
            return;
        }

        $this->attach($name);
        $this->updatedAt = $at;
    }

    public function untag(ObsessionName $name, DateTimeImmutable $at): void
    {
        $obsession = $this->find($name);

        if (null === $obsession) {
            return;
        }

        $this->obsessions->removeElement($obsession);
        $this->updatedAt = $at;
    }

    private function attach(ObsessionName $name): void
    {
        $this->obsessions->add(new NoteObsession($this, $name));
    }

    private function find(ObsessionName $name): ?NoteObsession
    {
        foreach ($this->obsessions as $obsession) {
            if ($obsession->name()->equals($name)) {
                return $obsession;
            }
        }

        return null;
    }
}
