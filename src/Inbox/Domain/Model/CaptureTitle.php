<?php

declare(strict_types=1);

namespace App\Inbox\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * De quoi reconnaître une capture dans une liste.
 *
 * C'est une étiquette, pas un contenu : la première ligne, coupée. Le texte
 * capturé est conservé entier à côté — on abrège ce qui se lit d'un coup d'œil,
 * on ne perd rien de ce qui a été attrapé.
 */
final readonly class CaptureTitle implements Stringable
{
    private const int MAX_LENGTH = 70;

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromText(string $text): self
    {
        $firstLine = trim(strtok(trim($text), "\n") ?: '');

        if ('' === $firstLine) {
            throw new InvalidArgumentException('Une capture vide n\'a rien à trier.');
        }

        return new self(mb_substr($firstLine, 0, self::MAX_LENGTH));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
