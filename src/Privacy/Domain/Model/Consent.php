<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Model;

/**
 * Traitements facultatifs, chacun soumis à un consentement distinct.
 *
 * Distincts, précisément : un consentement global ne serait ni libre ni
 * spécifique, et ne vaudrait donc rien.
 */
enum Consent: string
{
    case Assistant = 'assistant';
    case Usage = 'usage';
    case Backup = 'backup';
}
