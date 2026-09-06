<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Le secret que porte le lien d'invitation.
 *
 * Il ne suffit pas à entrer : l'adresse du compte qui accepte doit être celle
 * qui a été invitée. Un lien transféré ne fait donc entrer personne.
 */
final readonly class InvitationToken implements Stringable
{
    private const int LENGTH = 32;

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{'.self::LENGTH.'}$/', $value)) {
            throw new InvalidArgumentException('Ce jeton d\'invitation n\'a pas la forme attendue.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
