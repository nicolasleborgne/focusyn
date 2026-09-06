<?php

declare(strict_types=1);

namespace App\Assistant\Application\Exception;

use RuntimeException;

/**
 * Le fournisseur n'a pas répondu, ou a répondu non.
 *
 * Le message est destiné à être montré : il doit expliquer sans jamais
 * transporter la clé ni le contenu de la note.
 */
final class AssistantRefused extends RuntimeException
{
}
