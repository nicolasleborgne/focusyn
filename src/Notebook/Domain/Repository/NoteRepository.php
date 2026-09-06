<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Repository;

use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\ObsessionName;
use DateTimeImmutable;

/**
 * Les requêtes ne prennent pas d'organisation en paramètre : le cloisonnement
 * est appliqué par le filtre Doctrine, une seule fois, pour toute la requête
 * HTTP. Le répéter dans chaque méthode inviterait à l'oublier une fois.
 */
interface NoteRepository
{
    public function save(Note $note): void;

    public function remove(Note $note): void;

    public function ofId(NoteId $id): ?Note;

    /**
     * Notes les plus récemment modifiées d'abord.
     *
     * @return list<Note>
     */
    public function mostRecent(int $limit = 50): array;

    /** @return list<Note> */
    public function taggedWith(ObsessionName $obsession, int $limit = 50): array;

    /**
     * Recherche plein texte sur le titre et le corps.
     *
     * @return list<Note>
     */
    public function matching(string $query, int $limit = 50): array;

    public function count(): int;

    /**
     * Notes modifiées depuis une date, pour les décomptes de période.
     *
     * @return list<Note>
     */
    public function updatedSince(DateTimeImmutable $since): array;

    /**
     * Nombre de notes par obsession, de la plus fournie à la moins fournie.
     *
     * @return list<array{name: string, slug: string, count: int}>
     */
    public function obsessionCounts(): array;

    /**
     * La dernière fois que chaque obsession a été mentionnée.
     *
     * @return list<array{name: string, slug: string, lastMentionedAt: DateTimeImmutable}>
     */
    public function obsessionLastMentions(): array;
}
