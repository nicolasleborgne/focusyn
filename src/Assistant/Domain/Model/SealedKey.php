<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model;

use InvalidArgumentException;

/**
 * Une clé d'API telle qu'elle est conservée : déjà chiffrée.
 *
 * Le domaine ne voit jamais la clé en clair et n'a aucun moyen de la
 * déchiffrer. Le chiffrement appartient à l'infrastructure, la décision de ne
 * jamais stocker autre chose que du chiffré appartient au domaine, et ce type
 * est la frontière entre les deux.
 */
final readonly class SealedKey
{
    private function __construct(
        private string $cipher,
    ) {
    }

    public static function fromCipher(string $cipher): self
    {
        if ('' === trim($cipher)) {
            throw new InvalidArgumentException('Une clé scellée ne peut pas être vide.');
        }

        return new self($cipher);
    }

    public function cipher(): string
    {
        return $this->cipher;
    }
}
