<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Une session ouverte, telle qu'on la présente à son propriétaire.
 *
 * **Elle suit la personne, pas l'organisation** : on se connecte à un compte,
 * pas à une organisation. Son identifiant *est* celui de la session PHP, ce qui
 * permet de la révoquer réellement — la fermer à cet écran ferme la session
 * elle-même, elle ne fait pas que la retirer d'une liste.
 */
final class LoginSession extends AggregateRoot
{
    private function __construct(
        private readonly string $id,
        private readonly UserId $userId,
        private readonly Device $device,
        private readonly DateTimeImmutable $openedAt,
        private DateTimeImmutable $lastSeenAt,
    ) {
    }

    public static function open(
        string $id,
        UserId $userId,
        Device $device,
        DateTimeImmutable $now,
    ): self {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Une session doit avoir un identifiant.');
        }

        return new self($id, $userId, $device, $now, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function device(): Device
    {
        return $this->device;
    }

    public function openedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function lastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    /**
     * Deux requêtes concurrentes peuvent arriver dans le désordre : la plus
     * ancienne ne doit pas rajeunir la session.
     */
    public function seenAt(DateTimeImmutable $now): void
    {
        if ($now > $this->lastSeenAt) {
            $this->lastSeenAt = $now;
        }
    }
}
