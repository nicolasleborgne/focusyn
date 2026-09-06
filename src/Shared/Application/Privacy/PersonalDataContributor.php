<?php

declare(strict_types=1);

namespace App\Shared\Application\Privacy;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Ce qu'un contexte apporte à l'export et à l'effacement.
 *
 * Le RGPD traverse tout : porter les données d'une personne suppose de
 * rassembler ce que chaque contexte détient. Plutôt que de donner à Privacy le
 * droit de connaître Notebook et Task, chaque contexte se déclare ici.
 *
 * Un contexte ajouté plus tard n'a qu'à implémenter cette interface pour
 * entrer dans l'export — sans qu'aucun code de Privacy ne change. C'est
 * exactement ce qui évite qu'un export oublie silencieusement des données.
 */
#[AutoconfigureTag('app.personal_data_contributor')]
interface PersonalDataContributor
{
    /**
     * Clé de la section dans l'export : « notes », « taches »….
     */
    public function section(): string;

    /**
     * Données exportables de l'organisation courante, en primitives.
     *
     * @return list<array<string, mixed>>
     */
    public function export(): array;

    /**
     * Efface tout ce que ce contexte détient pour l'organisation courante.
     */
    public function erase(): void;
}
