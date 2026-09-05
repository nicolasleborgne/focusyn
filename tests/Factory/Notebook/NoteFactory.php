<?php

declare(strict_types=1);

namespace App\Tests\Factory\Notebook;

use App\Notebook\Domain\Model\AuthorId;
use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteBody;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\NoteTitle;
use App\Notebook\Domain\Model\ObsessionName;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Note> */
final class NoteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Note::class;
    }

    public function ownedBy(TenantId $tenant): static
    {
        return $this->with(['tenantId' => $tenant]);
    }

    public function titled(string $title): static
    {
        return $this->with(['title' => NoteTitle::fromString($title)]);
    }

    public function withBody(string $body): static
    {
        return $this->with(['body' => NoteBody::fromString($body)]);
    }

    /** @param list<string> $names */
    public function about(array $names): static
    {
        return $this->with([
            'obsessions' => array_map(ObsessionName::fromString(...), $names),
        ]);
    }

    protected function defaults(): array
    {
        return [
            'id' => NoteId::generate(),
            'tenantId' => TenantId::generate(),
            'authorId' => AuthorId::generate(),
            'title' => NoteTitle::fromString(ucfirst(self::faker()->unique()->sentence(4))),
            'body' => NoteBody::fromString(self::faker()->paragraph()),
            'obsessions' => [],
            'writtenAt' => DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(
            /** @param array{id: NoteId, tenantId: TenantId, authorId: AuthorId, title: NoteTitle, body: NoteBody, obsessions: list<ObsessionName>, writtenAt: DateTimeImmutable} $parameters */
            static fn (array $parameters): Note => Note::write(
                $parameters['id'],
                $parameters['tenantId'],
                $parameters['authorId'],
                $parameters['title'],
                $parameters['body'],
                $parameters['obsessions'],
                $parameters['writtenAt'],
            ),
        );
    }
}
