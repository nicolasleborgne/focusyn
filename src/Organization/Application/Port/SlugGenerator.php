<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

/**
 * Transformation d'un intitulé en fragment d'URL.
 *
 * Port et non fonction du domaine : la translittération correcte des accents et
 * des alphabets non latins dépend d'ICU, que le domaine n'a pas à connaître.
 */
interface SlugGenerator
{
    public function slugify(string $text): string;
}
