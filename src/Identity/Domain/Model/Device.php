<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use Stringable;

/**
 * L'appareil derrière une session, tel qu'il se présente.
 *
 * On ne retient que le navigateur et le système : c'est ce qui permet de
 * reconnaître « celui-là, c'est le mien » sans conserver la chaîne complète du
 * navigateur, qui est une empreinte.
 *
 * Rien n'est déduit d'une adresse IP — ni ville, ni pays. La maquette annonce
 * « MacBook Air — Paris » ; une localisation devinée qui se trompe est pire
 * qu'une localisation absente quand il s'agit de décider d'une révocation.
 */
final readonly class Device implements Stringable
{
    private const string UNKNOWN = 'Appareil inconnu';

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromUserAgent(string $userAgent): self
    {
        $browser = self::browserOf($userAgent);
        $system = self::systemOf($userAgent);

        return new self(
            null === $browser || null === $system ? self::UNKNOWN : $browser.' — '.$system,
        );
    }

    public static function fromString(string $value): self
    {
        return new self('' === trim($value) ? self::UNKNOWN : mb_substr(trim($value), 0, 120));
    }

    public function toString(): string
    {
        return $this->value;
    }

    private static function browserOf(string $userAgent): ?string
    {
        return match (true) {
            // L'ordre compte : Edge et Chrome se réclament tous deux de Safari.
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };
    }

    private static function systemOf(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }
}
