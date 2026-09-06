<?php

declare(strict_types=1);

namespace App\Inbox\Domain\Model;

/**
 * Ce qu'on a attrapé : une adresse, ou du texte.
 *
 * La nature se déduit de ce qui arrive, elle ne se déclare pas — celui qui
 * partage un article depuis son navigateur ne va pas cocher « lien ». Elle ne
 * sert qu'à l'affichage : une adresse seule n'annonce rien d'elle-même, et
 * l'étiquette dit alors ce qui attend d'être ouvert.
 */
enum CaptureKind: string
{
    case Link = 'link';
    case Text = 'text';

    /**
     * Une adresse **seule sur sa ligne** fait un lien.
     *
     * Ligne par ligne, et non sur le tout : un partage entrant arrive comme un
     * titre suivi de son adresse, et c'est bien un lien. Mais une phrase qui
     * cite une adresse reste une phrase — l'étiqueter « lien » ferait attendre
     * un article là où il n'y a qu'une remarque.
     */
    public static function of(string $text): self
    {
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (1 === preg_match('#^https?://\S+$#', trim($line))) {
                return self::Link;
            }
        }

        return self::Text;
    }
}
