<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

/**
 * Un fragment de ligne, avec ses marques.
 *
 * `before` et `after` portent les astérisques, accents graves ou crochets :
 * ils sont rendus dans une teinte pâle plutôt que retirés, c'est le parti pris
 * de Focusyn. En aperçu, ils sont vides.
 */
final readonly class ProseSegment
{
    public function __construct(
        public string $text,
        public string $style = 'plain',
        public string $before = '',
        public string $after = '',
        public ?string $href = null,
    ) {
    }
}
