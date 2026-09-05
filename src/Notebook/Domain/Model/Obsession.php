<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use App\Notebook\Domain\Exception\TooManyObsessionPoints;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TenantScoped;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Fiche éditoriale d'une obsession.
 *
 * Une obsession *existe* dès qu'une note la mentionne : elle n'a pas besoin
 * d'être créée. Cet agrégat ne porte que ce qu'on choisit d'en écrire — une
 * accroche et quelques points. Il est donc facultatif, et son absence est un
 * état normal, pas une donnée manquante.
 *
 * C'est ce qui évite toute synchronisation : étiqueter une note ne crée rien,
 * supprimer la dernière note d'une obsession ne casse rien.
 */
final class Obsession extends AggregateRoot implements TenantScoped
{
    private const int MAX_POINTS = 6;

    /** @var list<ObsessionPoint> */
    private array $points = [];

    private function __construct(
        private readonly ObsessionId $id,
        private readonly TenantId $tenantId,
        private ObsessionName $name,
        private readonly string $slug,
        private ?ObsessionBlurb $blurb,
        private readonly DateTimeImmutable $describedAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<ObsessionPoint> $points
     */
    public static function describe(
        ObsessionId $id,
        TenantId $tenantId,
        ObsessionName $name,
        ?ObsessionBlurb $blurb,
        array $points,
        DateTimeImmutable $describedAt,
    ): self {
        $obsession = new self($id, $tenantId, $name, $name->slug(), $blurb, $describedAt, $describedAt);
        $obsession->points = self::accept($points);

        return $obsession;
    }

    public function id(): ObsessionId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): ObsessionName
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function blurb(): ?ObsessionBlurb
    {
        return $this->blurb;
    }

    /** @return list<ObsessionPoint> */
    public function points(): array
    {
        return $this->points;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Une fiche vide vaut une absence de fiche : l'écran n'affichera rien.
     */
    public function saysSomething(): bool
    {
        return null !== $this->blurb || [] !== $this->points;
    }

    public function rewriteBlurb(?ObsessionBlurb $blurb, DateTimeImmutable $at): void
    {
        if ($this->blurb?->toString() === $blurb?->toString()) {
            return;
        }

        $this->blurb = $blurb;
        $this->updatedAt = $at;
    }

    /**
     * @param list<ObsessionPoint> $points
     */
    public function replacePoints(array $points, DateTimeImmutable $at): void
    {
        $accepted = self::accept($points);

        if ($this->pointTexts() === array_map(static fn (ObsessionPoint $p): string => $p->toString(), $accepted)) {
            return;
        }

        $this->points = $accepted;
        $this->updatedAt = $at;
    }

    /**
     * Rectifie la graphie — « Cafe » devient « Café ». Le fragment d'URL ne
     * bouge pas : il identifie la fiche, et les liens déjà partagés doivent
     * continuer de fonctionner.
     */
    public function rename(ObsessionName $name, DateTimeImmutable $at): void
    {
        if ($name->slug() !== $this->slug) {
            throw new InvalidArgumentException('Renommer une obsession vers un autre sujet reviendrait à en changer l\'identité.');
        }

        if ($this->name->toString() === $name->toString()) {
            return;
        }

        $this->name = $name;
        $this->updatedAt = $at;
    }

    /**
     * @param list<ObsessionPoint> $points
     *
     * @return list<ObsessionPoint>
     */
    private static function accept(array $points): array
    {
        if (\count($points) > self::MAX_POINTS) {
            throw TooManyObsessionPoints::limitedTo(self::MAX_POINTS);
        }

        return array_values($points);
    }

    /** @return list<string> */
    private function pointTexts(): array
    {
        return array_map(static fn (ObsessionPoint $point): string => $point->toString(), $this->points);
    }
}
