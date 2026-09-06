<?php

declare(strict_types=1);

namespace App\Shared\Application\Notebook;

/**
 * Écrire une note depuis un autre contexte.
 *
 * Déclaré ici et implémenté par Notebook : la boîte de réception doit pouvoir
 * transformer une capture en note sans connaître ce qu'est une note, ni un
 * titre, ni une obsession.
 *
 * Ne transporte que des primitives, et rend l'identifiant de ce qui vient
 * d'être écrit — l'appelant doit pouvoir y conduire tout de suite, ce qu'un
 * événement, arrivant plus tard, ne permettrait pas.
 */
interface NoteWriter
{
    /** @return string l'identifiant de la note écrite */
    public function write(string $title, string $body): string;
}
