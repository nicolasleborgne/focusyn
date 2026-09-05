<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Corps d'une note, en markdown brut.
 *
 * Le markdown n'est jamais converti ici : Focusyn affiche la source avec ses
 * marques atténuées. Le domaine n'en tire que ce qui a un sens métier — un
 * nombre de mots, un temps de lecture, un extrait pour les listes.
 */
final readonly class NoteBody implements Stringable
{
    private const int MAX_LENGTH = 200_000;
    private const int EXCERPT_LENGTH = 160;
    private const int WORDS_PER_MINUTE = 200;

    private function __construct(
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function empty(): self
    {
        return new self('');
    }

    public static function fromString(string $value): self
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Le corps d\'une note dépasse %d caractères.', self::MAX_LENGTH));
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function wordCount(): int
    {
        $words = preg_split('/\s+/u', $this->withoutMarkdownMarks(), -1, \PREG_SPLIT_NO_EMPTY);

        return false === $words ? 0 : \count($words);
    }

    public function readingMinutes(): int
    {
        return max(1, (int) round($this->wordCount() / self::WORDS_PER_MINUTE));
    }

    /**
     * Première ligne de prose, tronquée sur une frontière de mot.
     *
     * Les titres, citations et puces sont écartés : un extrait fait de « # »
     * et de « > » ne dirait rien du contenu.
     */
    public function excerpt(): string
    {
        foreach (explode("\n", $this->value) as $line) {
            $trimmed = trim($line);

            if ('' === $trimmed || $this->isDecoration($trimmed)) {
                continue;
            }

            return $this->truncate($this->stripInlineMarks($trimmed));
        }

        return '';
    }

    private function isDecoration(string $line): bool
    {
        return 1 === preg_match('/^(#{1,6}\s|>|[-*]\s|\d+\.\s|```|---+$)/u', $line);
    }

    private function withoutMarkdownMarks(): string
    {
        $withoutBlockMarks = preg_replace(
            '/^\s*(#{1,6}\s+|>\s?|[-*]\s+|\d+\.\s+|```.*)/mu',
            '',
            $this->value,
        ) ?? $this->value;

        return $this->stripInlineMarks($withoutBlockMarks);
    }

    private function stripInlineMarks(string $text): string
    {
        return preg_replace('/[*`_]+/u', '', $text) ?? $text;
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::EXCERPT_LENGTH - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim(false === $lastSpace ? $cut : mb_substr($cut, 0, $lastSpace)).'…';
    }
}
