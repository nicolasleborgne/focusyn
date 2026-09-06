<?php

declare(strict_types=1);

namespace App\Routine\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TenantScoped;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;

/**
 * Ce qu'on refait, et à quel rythme.
 *
 * Deux règles font tout le reste. **Ce qui est dû** un jour donné dépend du
 * calendrier de chaque ligne. **La période** dans laquelle un cochage compte
 * dépend de la cadence : cocher n'efface rien, cela vaut jusqu'à la période
 * suivante, où tout se repose. C'est ce qui distingue une routine d'une liste
 * de tâches, où une tâche cochée le reste.
 *
 * Les cochages sont conservés, pas seulement ceux de la période en cours : sans
 * eux la série n'existerait pas, et une série qu'on ne peut pas justifier ne
 * vaut rien.
 *
 * Une routine ne connaît pas l'obsession qu'elle sert : elle en retient le nom,
 * comme un rappel retient un sujet. Étiqueter ne crée rien, et une obsession
 * effacée ne casse aucune routine.
 */
final class Routine extends AggregateRoot implements TenantScoped
{
    /**
     * Au-delà, on cesse de remonter le temps.
     *
     * Une série de mille jours ne se lit pas autrement qu'une série de trois
     * cents, et compter jusque-là coûterait autant de tours de boucle à chaque
     * affichage de la barre latérale.
     */
    private const int STREAK_HORIZON = 365;

    /** @var Collection<int, RoutineItem> */
    private Collection $items;

    /** @var Collection<int, RoutineTick> */
    private Collection $ticks;

    private ?string $obsession = null;

    private function __construct(
        private readonly RoutineId $id,
        private readonly TenantId $tenantId,
        private RoutineName $name,
        private Cadence $cadence,
        private readonly DateTimeImmutable $openedAt,
        private DateTimeImmutable $updatedAt,
    ) {
        $this->items = new ArrayCollection();
        $this->ticks = new ArrayCollection();
    }

    public static function open(
        RoutineId $id,
        TenantId $tenantId,
        RoutineName $name,
        Cadence $cadence,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $tenantId, $name, $cadence, $now, $now);
    }

    public function id(): RoutineId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): RoutineName
    {
        return $this->name;
    }

    public function cadence(): Cadence
    {
        return $this->cadence;
    }

    public function obsession(): ?string
    {
        return $this->obsession;
    }

    public function openedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<RoutineItem> */
    public function items(): array
    {
        $items = array_values($this->items->toArray());
        usort($items, static fn (RoutineItem $a, RoutineItem $b): int => $a->position() <=> $b->position());

        return $items;
    }

    public function rename(RoutineName $name, DateTimeImmutable $at): void
    {
        $this->name = $name;
        $this->updatedAt = $at;
    }

    /**
     * Changer de cadence **oublie les calendriers**, pas les lignes.
     *
     * Des jours réglés pour une routine hebdomadaire ne veulent plus rien dire
     * une fois quotidienne, et un rang mensuel encore moins ; les garder ferait
     * réapparaître des réglages invisibles le jour où l'on revient en arrière.
     * Les cochages, eux, sont vidés : ils étaient rangés par période, et les
     * périodes viennent de changer de nature.
     */
    public function changeCadence(Cadence $cadence, DateTimeImmutable $at): void
    {
        if ($cadence === $this->cadence) {
            return;
        }

        $this->cadence = $cadence;
        $this->ticks->clear();

        foreach ($this->items as $item) {
            $item->schedule([], null);
        }

        $this->updatedAt = $at;
    }

    /** Le nom d'une obsession, ou rien. Une chaîne vide vaut « rien ». */
    public function serve(?string $obsession, DateTimeImmutable $at): void
    {
        $name = null === $obsession ? null : trim($obsession);

        $this->obsession = '' === $name ? null : $name;
        $this->updatedAt = $at;
    }

    public function addItem(RoutineItemId $id, RoutineText $text, DateTimeImmutable $at): void
    {
        $this->items->add(new RoutineItem($this, $id, $text, $this->items->count(), $at));
        $this->updatedAt = $at;
    }

    public function removeItem(RoutineItemId $id, DateTimeImmutable $at): void
    {
        $item = $this->item($id);

        foreach ($this->ticks as $tick) {
            if ($tick->itemId()->equals($id)) {
                $this->ticks->removeElement($tick);
            }
        }

        $this->items->removeElement($item);
        $this->updatedAt = $at;
    }

    /** @param list<int> $days */
    public function scheduleItem(RoutineItemId $id, array $days, ?int $rank, ?DateTimeImmutable $at = null): void
    {
        $this->item($id)->schedule($days, $rank);
        $this->updatedAt = $at ?? $this->updatedAt;
    }

    /**
     * Ce qui est attendu ce jour-là.
     *
     * @return list<RoutineItem>
     */
    public function dueOn(DateTimeImmutable $day): array
    {
        return array_values(array_filter(
            $this->items(),
            fn (RoutineItem $item): bool => $item->isDueOn($day, $this->cadence),
        ));
    }

    public function isTicked(RoutineItemId $id, DateTimeImmutable $moment): bool
    {
        return null !== $this->tickAt($id, $this->cadence->periodOf($moment));
    }

    /** Coche, ou décoche si c'était déjà fait : le même geste dans les deux sens. */
    public function tick(RoutineItemId $id, DateTimeImmutable $at): void
    {
        $item = $this->item($id);
        $period = $this->cadence->periodOf($at);
        $existing = $this->tickAt($id, $period);

        if (null !== $existing) {
            $this->ticks->removeElement($existing);
        } else {
            $this->ticks->add(new RoutineTick($this, RoutineTickId::generate(), $item->id(), $period, $at));
        }

        $this->updatedAt = $at;
    }

    /** Combien de lignes restent à faire aujourd'hui. */
    public function remainingOn(DateTimeImmutable $day): int
    {
        return \count(array_filter(
            $this->dueOn($day),
            fn (RoutineItem $item): bool => !$this->isTicked($item->id(), $day),
        ));
    }

    /**
     * Depuis combien de périodes pleines la routine se tient.
     *
     * On remonte tant qu'une période a été **entièrement** faite. La période en
     * cours ne compte que si elle est déjà finie — sinon la série tomberait à
     * zéro chaque matin, avant qu'on ait eu le temps de cocher quoi que ce soit.
     *
     * La série se calcule sur les lignes **d'aujourd'hui** : ce qui était dû il
     * y a trois mois est irrécupérable, les lignes ayant pu changer depuis. Elle
     * répond donc à « depuis quand tiens-tu la routine telle qu'elle est ? »,
     * ce qui est la seule question à laquelle on puisse répondre honnêtement.
     */
    public function streakOn(DateTimeImmutable $now): int
    {
        if ([] === $this->items()) {
            return 0;
        }

        $streak = 0;
        $moment = $now;

        if (!$this->isWhollyDoneOn($moment)) {
            // Aujourd'hui n'est pas fini : on regarde ce qui précède, sans le
            // compter comme un échec.
            $moment = $this->cadence->previous($moment);
        }

        while ($streak < self::STREAK_HORIZON && $this->isWhollyDoneOn($moment)) {
            ++$streak;
            $moment = $this->cadence->previous($moment);
        }

        return $streak;
    }

    /** @return list<RoutineTick> */
    public function ticks(): array
    {
        return array_values($this->ticks->toArray());
    }

    /**
     * Une période où rien n'était dû n'est pas une réussite : c'est un dimanche
     * sans routine, qui ne doit ni allonger la série ni la rompre. Comme elle ne
     * peut pas faire les deux, on choisit de la rompre — une série qui traverse
     * des périodes vides ne dit plus rien de ce qu'on tient.
     */
    private function isWhollyDoneOn(DateTimeImmutable $moment): bool
    {
        $due = $this->dueOn($moment);

        if ([] === $due) {
            return false;
        }

        foreach ($due as $item) {
            if (!$this->isTicked($item->id(), $moment)) {
                return false;
            }
        }

        return true;
    }

    private function tickAt(RoutineItemId $id, string $period): ?RoutineTick
    {
        foreach ($this->ticks as $tick) {
            if ($tick->itemId()->equals($id) && $tick->period() === $period) {
                return $tick;
            }
        }

        return null;
    }

    private function item(RoutineItemId $id): RoutineItem
    {
        foreach ($this->items as $item) {
            if ($item->id()->equals($id)) {
                return $item;
            }
        }

        throw new InvalidArgumentException('Cette ligne n\'appartient pas à la routine.');
    }
}
