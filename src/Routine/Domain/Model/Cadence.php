<?php

declare(strict_types=1);

namespace App\Routine\Domain\Model;

use DateTimeImmutable;

/**
 * À quel rythme une routine se repose.
 *
 * C'est la cadence qui définit la **période** : l'intervalle pendant lequel un
 * cochage tient. Cocher n'efface rien — cela vaut jusqu'à la période suivante,
 * où tout se repose. C'est ce qui distingue une routine d'une liste de tâches.
 */
enum Cadence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * L'étiquette de la période qui contient ce moment.
     *
     * Une chaîne, comparée telle quelle : deux cochages sont dans la même
     * période si et seulement si leurs étiquettes sont égales. La semaine part
     * du lundi, comme partout en France ; `o-W` s'en charge, y compris pour les
     * jours de fin décembre qui appartiennent à la semaine de l'année suivante.
     */
    public function periodOf(DateTimeImmutable $moment): string
    {
        return match ($this) {
            self::Daily => $moment->format('Y-m-d'),
            self::Weekly => $moment->format('o-\WW'),
            self::Monthly => $moment->format('Y-m'),
        };
    }

    /** Le même moment, une période plus tôt. */
    public function previous(DateTimeImmutable $moment): DateTimeImmutable
    {
        return match ($this) {
            self::Daily => $moment->modify('-1 day'),
            self::Weekly => $moment->modify('-7 days'),
            // Le premier du mois d'abord : reculer d'un mois depuis le 31 mars
            // donnerait le 3 mars, PHP débordant sur les jours manquants.
            self::Monthly => $moment->modify('first day of last month'),
        };
    }

    public function schedulesDays(): bool
    {
        return self::Daily !== $this;
    }

    public function schedulesRank(): bool
    {
        return self::Monthly === $this;
    }
}
