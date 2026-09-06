<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Shared\Application\Privacy\PersonalDataContributor;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Rassemble les données exportables de tous les contextes.
 *
 * Aucune liste codée en dur : un contexte entre dans l'export du seul fait
 * d'implémenter le port. Un export qui oublierait un contexte serait un
 * manquement, pas un détail.
 */
final readonly class PersonalDataExport
{
    /** @param iterable<PersonalDataContributor> $contributors */
    public function __construct(
        #[AutowireIterator('app.personal_data_contributor')]
        private iterable $contributors,
    ) {
    }

    /** @return array<string, mixed> */
    public function gather(string $accountEmail): array
    {
        $sections = [];

        foreach ($this->contributors as $contributor) {
            $sections[$contributor->section()] = $contributor->export();
        }

        return [
            'exportePar' => 'Focusyn',
            'exporteLe' => (new DateTimeImmutable())->format(\DATE_ATOM),
            'compte' => $accountEmail,
            'schema' => 1,
            ...$sections,
        ];
    }

    /**
     * Résumé affiché avant le téléchargement : on annonce ce qu'on emporte.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        $counts = [];

        foreach ($this->contributors as $contributor) {
            $counts[$contributor->section()] = \count($contributor->export());
        }

        return $counts;
    }
}
