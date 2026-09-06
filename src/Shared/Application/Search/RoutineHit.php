<?php

declare(strict_types=1);

namespace App\Shared\Application\Search;

final readonly class RoutineHit
{
    public function __construct(
        public string $routineId,
        public string $routineName,
        /** L'étape qui a répondu, ou le nom de la routine quand c'est lui. */
        public string $text,
        public bool $ticked,
        /**
         * Vrai quand c'est le nom qui a répondu.
         *
         * L'écran s'en sert pour ne pas répéter le nom à droite de lui-même —
         * une ligne « Revue de la semaine · Revue de la semaine » n'apprend
         * rien et se lit comme un bug.
         */
        public bool $matchedName = false,
    ) {
    }
}
