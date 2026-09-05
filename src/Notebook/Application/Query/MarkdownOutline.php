<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

use App\Notebook\Domain\Model\NoteBody;

/**
 * Découpe un corps de note en lignes affichables.
 *
 * Focusyn ne convertit pas le markdown en HTML : il affiche la source, chaque
 * ligne mise en forme selon sa nature, marques comprises. L'aperçu emploie
 * exactement le même découpage avec `withMarks: false` — c'est ce qui garantit
 * que les deux colonnes se correspondent ligne pour ligne.
 *
 * Un seul analyseur, en PHP. L'éditeur, lui, s'appuie sur celui de CodeMirror ;
 * en écrire un second en JavaScript pour l'aperçu créerait deux vérités qui
 * finiraient par diverger.
 */
final readonly class MarkdownOutline
{
    private const string INLINE = '/(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/u';

    /** @return list<ProseLine> */
    public function lines(NoteBody $body, bool $withMarks = true): array
    {
        if ($body->isEmpty()) {
            return [];
        }

        $lines = [];
        $fenced = false;

        foreach (explode("\n", $body->toString()) as $raw) {
            $trimmed = trim($raw);

            if (str_starts_with($trimmed, '```')) {
                $fenced = !$fenced;
                $lines[] = new ProseLine('code', '', [new ProseSegment($withMarks ? $raw : '')], $raw);

                continue;
            }

            if ($fenced) {
                // À l'intérieur d'un bloc, les astérisques sont du code.
                $lines[] = new ProseLine('code', '', [new ProseSegment($raw)], $raw);

                continue;
            }

            $lines[] = $this->outline($raw, $trimmed, $withMarks);
        }

        return $lines;
    }

    private function outline(string $raw, string $trimmed, bool $withMarks): ProseLine
    {
        if ('' === $trimmed) {
            return new ProseLine('blank', '', [], $raw);
        }

        if (1 === preg_match('/^-{3,}$/', $trimmed)) {
            return new ProseLine('rule', $withMarks ? $trimmed : '', [], $raw);
        }

        if (1 === preg_match('/^(#{1,3})\s+(.*)$/u', $trimmed, $heading)) {
            return new ProseLine(
                'h'.\strlen($heading[1]),
                $withMarks ? $heading[1].' ' : '',
                $this->segments($heading[2], $withMarks),
                $raw,
            );
        }

        if (1 === preg_match('/^>\s?(.*)$/u', $trimmed, $quote)) {
            return new ProseLine('quote', $withMarks ? '> ' : '', $this->segments($quote[1], $withMarks), $raw);
        }

        if (1 === preg_match('/^([-*])\s+(.*)$/u', $trimmed, $bullet)) {
            // Sans marque, la puce est remplacée et non retirée : une liste
            // sans puces cesserait de se lire comme une liste.
            return new ProseLine('list', $withMarks ? $bullet[1].' ' : '•  ', $this->segments($bullet[2], $withMarks), $raw);
        }

        if (1 === preg_match('/^(\d+\.)\s+(.*)$/u', $trimmed, $ordered)) {
            return new ProseLine('list', $ordered[1].'  ', $this->segments($ordered[2], $withMarks), $raw);
        }

        return new ProseLine('paragraph', '', $this->segments($trimmed, $withMarks), $raw);
    }

    /** @return list<ProseSegment> */
    private function segments(string $text, bool $withMarks): array
    {
        $parts = preg_split(self::INLINE, $text, -1, \PREG_SPLIT_DELIM_CAPTURE);

        if (false === $parts) {
            return [new ProseSegment($text)];
        }

        $segments = [];

        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }

            $segments[] = $this->segment($part, $withMarks);
        }

        return [] === $segments ? [new ProseSegment("\u{a0}")] : $segments;
    }

    private function segment(string $part, bool $withMarks): ProseSegment
    {
        $marks = static fn (string $mark): string => $withMarks ? $mark : '';

        if (str_starts_with($part, '**') && str_ends_with($part, '**')) {
            return new ProseSegment(mb_substr($part, 2, -2), 'strong', $marks('**'), $marks('**'));
        }

        if (str_starts_with($part, '`') && str_ends_with($part, '`')) {
            return new ProseSegment(mb_substr($part, 1, -1), 'code', $marks('`'), $marks('`'));
        }

        if (1 === preg_match('/^\[([^\]]+)\]\(([^)]+)\)$/u', $part, $link)) {
            return new ProseSegment($link[1], 'link', $marks('['), $marks(']('.$link[2].')'), $link[2]);
        }

        if (str_starts_with($part, '*') && str_ends_with($part, '*')) {
            return new ProseSegment(mb_substr($part, 1, -1), 'emphasis', $marks('*'), $marks('*'));
        }

        return new ProseSegment($part);
    }
}
