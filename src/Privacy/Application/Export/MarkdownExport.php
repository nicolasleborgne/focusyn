<?php

declare(strict_types=1);

namespace App\Privacy\Application\Export;

/**
 * Export lisible par un humain, et réutilisable ailleurs.
 *
 * Le JSON sert à réimporter ; le Markdown sert à relire dans dix ans sans
 * Focusyn. Les deux comptent : la portabilité n'est pas seulement une affaire
 * de machines.
 */
final readonly class MarkdownExport
{
    /** @param array<string, mixed> $data */
    public function render(array $data): string
    {
        $out = ['# Focusyn — export du '.($data['exporteLe'] ?? ''), ''];
        $out[] = 'Compte : '.($data['compte'] ?? '');
        $out[] = '';

        /** @var list<array<string, mixed>> $notes */
        $notes = $data['notes'] ?? [];

        if ([] !== $notes) {
            $out[] = '## Notes';
            $out[] = '';

            foreach ($notes as $note) {
                $out[] = '### '.($note['titre'] ?? '');
                $out[] = '';

                // Une ligne de méta, et seulement si elle dit quelque chose.
                $obsessions = (array) ($note['obsessions'] ?? []);
                $out[] = '*'.implode(' · ', array_filter([
                    (string) ($note['modifieeLe'] ?? ''),
                    [] === $obsessions ? '' : implode(', ', $obsessions),
                ])).'*';
                $out[] = '';
                $out[] = (string) ($note['corps'] ?? '');
                $out[] = '';
            }
        }

        /** @var list<array<string, mixed>> $lists */
        $lists = $data['taches'] ?? [];

        if ([] !== $lists) {
            $out[] = '## Tâches';
            $out[] = '';

            foreach ($lists as $list) {
                $out[] = '### '.($list['nom'] ?? '');
                $out[] = '';

                foreach ((array) ($list['taches'] ?? []) as $task) {
                    $out[] = \sprintf('- [%s] %s', ($task['faite'] ?? false) ? 'x' : ' ', $task['texte'] ?? '');
                }

                $out[] = '';
            }
        }

        return implode("\n", $out)."\n";
    }
}
