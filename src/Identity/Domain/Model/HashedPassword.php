<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use InvalidArgumentException;
use SensitiveParameter;
use Stringable;

/**
 * Empreinte d'un mot de passe, jamais le mot de passe lui-même.
 *
 * Le domaine ne sait pas hacher — c'est un choix d'infrastructure, dépendant
 * d'un algorithme et de son coût. Il reçoit une empreinte déjà calculée par un
 * port de la couche Application, et se contente de la transporter.
 */
final readonly class HashedPassword implements Stringable
{
    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Masque l'empreinte dans les dumps et le profileur : elle n'a rien à faire
     * dans une capture d'écran de débogage.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    public static function fromHash(#[SensitiveParameter] string $hash): self
    {
        if ('' === trim($hash)) {
            throw new InvalidArgumentException('Une empreinte de mot de passe ne peut pas être vide.');
        }

        return new self($hash);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
