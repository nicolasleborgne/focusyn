<?php

declare(strict_types=1);

namespace App\Inbox\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TenantScoped;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Ce qui est entré dans le carnet sans être encore rangé.
 *
 * Une capture n'est pas une note en attente : c'est de la matière venue
 * d'ailleurs — une adresse partagée depuis un navigateur, une phrase collée —
 * dont on ne sait pas encore si elle deviendra une note, une tâche, ou rien.
 *
 * **Elle ignore ce qu'elle deviendra**, comme un rappel ignore la note qu'il
 * porte. Trier ne la transforme pas : cela crée autre chose ailleurs, et la
 * capture disparaît. Une boîte est un sas, pas un journal — la garder « classée »
 * ferait d'un lieu qu'on doit pouvoir vider un lieu qui ne se vide jamais.
 *
 * Rien ici n'est modifiable : une capture est reçue telle quelle. On la trie ou
 * on l'écarte, on ne la corrige pas — corriger, c'est déjà l'avoir sortie de la
 * boîte.
 */
final class Capture extends AggregateRoot implements TenantScoped
{
    private function __construct(
        private readonly CaptureId $id,
        private readonly TenantId $tenantId,
        private readonly CaptureKind $kind,
        private readonly CaptureTitle $title,
        private readonly string $body,
        private readonly CaptureSource $source,
        private readonly DateTimeImmutable $capturedAt,
    ) {
    }

    public static function receive(
        CaptureId $id,
        TenantId $tenantId,
        string $text,
        CaptureSource $source,
        DateTimeImmutable $now,
    ): self {
        $body = trim($text);

        if ('' === $body) {
            throw new InvalidArgumentException('Une capture vide n\'a rien à trier.');
        }

        return new self(
            $id,
            $tenantId,
            CaptureKind::of($body),
            CaptureTitle::fromText($body),
            $body,
            $source,
            $now,
        );
    }

    public function id(): CaptureId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function kind(): CaptureKind
    {
        return $this->kind;
    }

    public function title(): CaptureTitle
    {
        return $this->title;
    }

    /** Le texte capturé, entier : c'est lui qui deviendra le corps d'une note. */
    public function body(): string
    {
        return $this->body;
    }

    public function source(): CaptureSource
    {
        return $this->source;
    }

    public function capturedAt(): DateTimeImmutable
    {
        return $this->capturedAt;
    }
}
