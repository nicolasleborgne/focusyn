<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Secret partagé d'un authentificateur TOTP, en base32.
 *
 * Stocké en clair, comme l'exige l'algorithme : le serveur doit pouvoir
 * recalculer le code à chaque connexion. C'est pourquoi la valeur ne quitte
 * jamais le serveur après l'enrôlement, et n'apparaît dans aucun journal.
 */
final readonly class TotpSecret implements Stringable
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    public static function fromString(string $value): self
    {
        $normalised = mb_strtoupper(trim($value));

        if (mb_strlen($normalised) < 16) {
            throw new InvalidArgumentException('Un secret TOTP fait au moins 16 caractères.');
        }

        if (1 !== preg_match('/^['.self::ALPHABET.']+$/', $normalised)) {
            throw new InvalidArgumentException('Un secret TOTP ne contient que des caractères base32.');
        }

        return new self($normalised);
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Découpe en groupes de quatre pour la saisie manuelle, quand le
     * QR code n'est pas scannable.
     */
    public function grouped(): string
    {
        return implode(' ', str_split($this->value, 4));
    }
}
