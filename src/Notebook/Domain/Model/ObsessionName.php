<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use InvalidArgumentException;
use Stringable;
use Transliterator;

/**
 * Un sujet suivi dans la durée — ce que Focusyn appelle une obsession.
 *
 * La forme écrite est conservée telle que saisie (« Café », « À lire / relire »)
 * mais les comparaisons et les URL passent par une forme normalisée : sans quoi
 * « Mémoire » et « memoire » deviendraient deux obsessions distinctes dans la
 * barre latérale.
 */
final readonly class ObsessionName implements Stringable
{
    private const int MAX_LENGTH = 60;

    private function __construct(
        private string $value,
        private string $slug,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Une obsession doit avoir un nom.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Un nom d\'obsession dépasse %d caractères.', self::MAX_LENGTH));
        }

        $slug = self::slugify($trimmed);

        if ('' === $slug) {
            throw new InvalidArgumentException(\sprintf('« %s » ne contient aucun caractère utilisable.', $value));
        }

        return new self($trimmed, $slug);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function equals(self $other): bool
    {
        return $this->slug === $other->slug;
    }

    /**
     * Translittération par ICU, disponible via l'extension intl : une extension
     * PHP, pas un cadriciel — le domaine reste indépendant.
     */
    private static function slugify(string $value): string
    {
        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower');
        $latin = $transliterator?->transliterate($value);

        if (!\is_string($latin)) {
            $latin = mb_strtolower($value);
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $latin) ?? $latin;

        return trim($slug, '-');
    }
}
