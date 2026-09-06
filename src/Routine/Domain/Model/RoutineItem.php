<?php

declare(strict_types=1);

namespace App\Routine\Domain\Model;

use DateTimeImmutable;

/**
 * Une ligne de routine. Entité interne à l'agrégat `Routine`.
 *
 * Elle porte son propre calendrier, et non la routine entière : « rafraîchir le
 * levain » tous les jours et « relever le pH » le samedi cohabitent dans la
 * même routine. C'est ce que fait la maquette, et c'est ce qui évite d'avoir à
 * ouvrir trois routines pour trois rythmes voisins.
 *
 * Les jours sont en ISO-8601 — 1 lundi, 7 dimanche —, comme `format('N')`.
 */
final class RoutineItem
{
    /** @var list<int> */
    private array $days = [];

    /** 1 à 4, ou -1 pour « le dernier du mois ». Ignoré hors cadence mensuelle. */
    private ?int $rank = null;

    public function __construct(
        private readonly Routine $routine,
        private readonly RoutineItemId $id,
        private RoutineText $text,
        private readonly int $position,
        private readonly DateTimeImmutable $addedAt,
    ) {
    }

    public function routine(): Routine
    {
        return $this->routine;
    }

    public function id(): RoutineItemId
    {
        return $this->id;
    }

    public function text(): RoutineText
    {
        return $this->text;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function addedAt(): DateTimeImmutable
    {
        return $this->addedAt;
    }

    /** @return list<int> */
    public function days(): array
    {
        return $this->days;
    }

    public function rank(): ?int
    {
        return $this->rank;
    }

    public function rewrite(RoutineText $text): void
    {
        $this->text = $text;
    }

    /**
     * @param list<int> $days jours ISO ; hors 1-7, ils sont écartés
     */
    public function schedule(array $days, ?int $rank): void
    {
        $kept = array_values(array_unique(array_filter($days, static fn (int $day): bool => $day >= 1 && $day <= 7)));
        sort($kept);

        $this->days = $kept;
        $this->rank = null === $rank ? null : max(-1, min(4, $rank));
    }

    /**
     * Cette ligne est-elle attendue ce jour-là ?
     *
     * Sans jour nommé, elle l'est n'importe quel jour de sa période : « une
     * fois cette semaine, quand on veut ». La période, elle, ne bouge pas — le
     * cochage tiendra jusqu'au lundi suivant.
     */
    public function isDueOn(DateTimeImmutable $day, Cadence $cadence): bool
    {
        if (Cadence::Daily === $cadence) {
            return true;
        }

        if ([] === $this->days) {
            // Rien n'a été précisé : la ligne est due toute la semaine, ou la
            // première du mois — plus tôt on la voit, plus tôt elle se fait.
            return Cadence::Weekly === $cadence || 1 === self::rankOf($day);
        }

        if (!\in_array((int) $day->format('N'), $this->days, true)) {
            return false;
        }

        if (Cadence::Monthly !== $cadence) {
            return true;
        }

        return -1 === $this->rank
            ? self::isLastOccurrenceOfItsDay($day)
            : ($this->rank ?? 1) === self::rankOf($day);
    }

    /** Combien de fois ce jour de semaine s'est déjà produit dans le mois. */
    private static function rankOf(DateTimeImmutable $day): int
    {
        return intdiv((int) $day->format('j') - 1, 7) + 1;
    }

    /** Aucun autre jour de même nom ne suit dans le mois. */
    private static function isLastOccurrenceOfItsDay(DateTimeImmutable $day): bool
    {
        return (int) $day->format('j') + 7 > (int) $day->format('t');
    }
}
