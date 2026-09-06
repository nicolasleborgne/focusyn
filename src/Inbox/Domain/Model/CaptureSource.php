<?php

declare(strict_types=1);

namespace App\Inbox\Domain\Model;

/**
 * Par où c'est entré.
 *
 * Distinct de la nature : une adresse peut aussi bien être partagée depuis une
 * autre application que collée à la main, et savoir lequel des deux aide à s'en
 * souvenir au moment de trier.
 *
 * Un enum plutôt qu'un libellé libre : la provenance s'affiche, donc elle se
 * traduit, et aucun texte ne doit être écrit en dur.
 */
enum CaptureSource: string
{
    case TypedIn = 'typed_in';
    case Shared = 'shared';
}
