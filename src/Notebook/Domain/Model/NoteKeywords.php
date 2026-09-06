<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Model;

use Transliterator;

/**
 * Les mots d'une note qui peuvent en désigner une autre.
 *
 * Rien d'intelligent : des mots assez longs et assez rares pour vouloir dire
 * quelque chose. C'est volontairement grossier — le résultat n'est qu'une
 * suggestion sous l'éditeur, jamais un classement. Un rapprochement de trop
 * coûte un regard ; un rapprochement fondé sur « dans » ou « cette » ferait
 * douter de tous les autres.
 *
 * Les mots sont translittérés et mis en bas de casse : « Fermentation » et
 * « fermentation » sont le même mot, et l'accent ne doit pas séparer deux
 * notes qui parlent de la même chose.
 */
final readonly class NoteKeywords
{
    /**
     * En dessous, un mot est trop court pour être discriminant : « vide »,
     * « ratio », « jour » se retrouvent partout.
     */
    private const int MIN_LENGTH = 5;

    /**
     * Les mots trop courants pour rapprocher quoi que ce soit.
     *
     * Une liste tenue à la main plutôt qu'un calcul de fréquence : sur un
     * carnet de cinquante notes, la fréquence n'a rien de significatif, et une
     * liste se corrige quand un mot parasite se montre.
     */
    private const array STOP_WORDS = [
        'dans', 'pour', 'avec', 'cette', 'entre', 'plus', 'moins', 'sans',
        'mais', 'comme', 'tres', 'etre', 'avoir', 'faire', 'tout', 'toute',
        'leur', 'sont', 'etait', 'elle', 'donc', 'aussi', 'apres', 'avant',
        'note', 'notes', 'quand', 'alors', 'chaque', 'meme', 'autre', 'autres',
        'celui', 'celle', 'ceux', 'depuis', 'encore', 'toujours', 'jamais',
    ];

    /** @param list<string> $words triés, sans doublon */
    private function __construct(
        private array $words,
    ) {
    }

    public static function of(string $text): self
    {
        $normalised = self::normalise($text);
        $words = preg_split('/[^a-z0-9]+/', $normalised, flags: \PREG_SPLIT_NO_EMPTY) ?: [];

        $kept = array_values(array_unique(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= self::MIN_LENGTH
                && !\in_array($word, self::STOP_WORDS, true),
        )));

        sort($kept);

        return new self($kept);
    }

    /**
     * Les mots que deux notes ont en commun.
     *
     * @return list<string>
     */
    public function shared(self $other): array
    {
        return array_values(array_intersect($this->words, $other->words));
    }

    /** @return list<string> */
    public function toArray(): array
    {
        return $this->words;
    }

    /**
     * Translittération par ICU, comme pour le slug d'une obsession : une
     * extension PHP, pas un cadriciel — le domaine reste indépendant.
     */
    private static function normalise(string $text): string
    {
        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower');
        $latin = $transliterator?->transliterate($text);

        return \is_string($latin) ? $latin : mb_strtolower($text);
    }
}
