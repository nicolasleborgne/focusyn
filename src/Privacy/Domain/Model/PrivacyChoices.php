<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use DateTimeImmutable;

/**
 * Ce qu'une personne autorise, et combien de temps on garde ses archives.
 *
 * Rien n'est consenti par défaut : un consentement se donne, il ne se présume
 * pas. La date de chaque accord est conservée — c'est elle qui en fait la
 * preuve, et qui date sa révocation.
 */
final class PrivacyChoices extends AggregateRoot
{
    /**
     * Consentement donné → date de l'accord, au format ATOM.
     *
     * Des chaînes plutôt que des objets : la colonne est du JSON, et cette
     * forme s'y écrit et s'en relit sans conversion ni type sur mesure.
     *
     * @var array<string, string>
     */
    private array $granted = [];

    private function __construct(
        private readonly SubjectId $subjectId,
        private Retention $retention,
        private readonly DateTimeImmutable $openedAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function forSubject(SubjectId $subjectId, DateTimeImmutable $at): self
    {
        // La conservation la plus courte par défaut : à défaut de choix, on
        // garde le moins longtemps possible.
        return new self($subjectId, Retention::TwelveMonths, $at, $at);
    }

    public function subjectId(): SubjectId
    {
        return $this->subjectId;
    }

    public function retention(): Retention
    {
        return $this->retention;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function allows(Consent $consent): bool
    {
        return isset($this->granted[$consent->value]);
    }

    public function grantedAt(Consent $consent): ?DateTimeImmutable
    {
        $at = $this->granted[$consent->value] ?? null;

        return null === $at ? null : new DateTimeImmutable($at);
    }

    public function grant(Consent $consent, DateTimeImmutable $at): void
    {
        if ($this->allows($consent)) {
            return;
        }

        $this->granted[$consent->value] = $at->format(\DATE_ATOM);
        $this->updatedAt = $at;
    }

    public function withdraw(Consent $consent, DateTimeImmutable $at): void
    {
        if (!$this->allows($consent)) {
            return;
        }

        unset($this->granted[$consent->value]);
        $this->updatedAt = $at;
    }

    public function keepFor(Retention $retention, DateTimeImmutable $at): void
    {
        if ($this->retention === $retention) {
            return;
        }

        $this->retention = $retention;
        $this->updatedAt = $at;
    }

    /**
     * Consentements en vigueur, avec la date de chaque accord.
     *
     * @return array<string, string>
     */
    public function grantedConsents(): array
    {
        return $this->granted;
    }
}
