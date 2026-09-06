<?php

declare(strict_types=1);

namespace App\Shared\Application\Task;

/**
 * Ajouter une tâche à une liste existante, depuis un autre contexte.
 *
 * Déclaré ici et implémenté par Task, pour la même raison que `NoteWriter` :
 * la boîte de réception trie, elle ne sait pas ce qu'est une liste de tâches.
 *
 * La liste est nommée par l'appelant : deviner laquelle produirait une règle
 * invisible, et l'écran de la boîte a de quoi la faire choisir.
 */
interface TaskWriter
{
    public function add(string $taskListId, string $text): void;
}
