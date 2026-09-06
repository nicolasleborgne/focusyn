<?php

declare(strict_types=1);

namespace App\Routine\Domain\Model;

use DateTimeImmutable;

/**
 * Une ligne cochée, pour une période.
 *
 * La période est une étiquette (`2026-09-07`, `2026-W37`, `2026-09`) plutôt
 * qu'un intervalle : deux cochages appartiennent à la même période si et
 * seulement si leurs étiquettes sont égales, ce qui se compare en base aussi
 * bien qu'en mémoire.
 *
 * On garde l'instant du cochage à côté : il ne sert à rien pour décider, mais
 * il sera là le jour où l'on voudra savoir *quand* dans la journée une routine
 * se fait — ce que l'étiquette, elle, ne dira jamais.
 */
final class RoutineTick
{
    public function __construct(
        private readonly Routine $routine,
        private readonly RoutineTickId $id,
        private readonly RoutineItemId $itemId,
        private readonly string $period,
        private readonly DateTimeImmutable $tickedAt,
    ) {
    }

    public function routine(): Routine
    {
        return $this->routine;
    }

    public function id(): RoutineTickId
    {
        return $this->id;
    }

    public function itemId(): RoutineItemId
    {
        return $this->itemId;
    }

    public function period(): string
    {
        return $this->period;
    }

    public function tickedAt(): DateTimeImmutable
    {
        return $this->tickedAt;
    }
}
