<?php

declare(strict_types=1);

namespace App\Notebook\UI\LiveComponent;

use App\Notebook\Application\Command\DescribeObsession\DescribeObsession;
use App\Notebook\Application\Query\NotebookQuery;
use App\Shared\Application\Assistant\AssistantUnavailable;
use App\Shared\Application\Assistant\WritingAssistant;
use App\Shared\Application\Command\CommandBus;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * « Ce qui se dégage », écrit par l'assistant à partir des notes.
 *
 * **La proposition ne s'enregistre pas d'elle-même.** Ce qui est écrit à la
 * main ici est le fruit d'une lecture ; l'écraser sans demander ferait perdre
 * un travail qu'aucune machine ne refera. On propose, on garde ou on écarte.
 *
 * La matière est passée close au port : les notes ne sont lues qu'une fois le
 * consentement et le palier vérifiés, et c'est `Completion` qui en décide.
 */
#[AsLiveComponent(name: 'ObsessionSynthesis', template: 'components/ObsessionSynthesis.html.twig')]
final class ObsessionSynthesis
{
    use DefaultActionTrait;

    private const string INSTRUCTION = <<<'PROMPT'
        Voici toutes les notes d'un carnet portant la même obsession.
        Dégage ce qui s'en ressort, en trois à cinq lignes séparées par des
        retours à la ligne. Une ligne, une idée, à l'affirmative, sans puce ni
        numéro. Ne résume pas note par note : cherche ce qui se répond d'une
        note à l'autre, y compris les contradictions.
        PROMPT;

    /** Combien de lignes on retient d'une proposition. Six, comme l'agrégat. */
    private const int MAX_LINES = 6;

    #[LiveProp]
    public string $slug = '';

    /** @var list<string> */
    #[LiveProp]
    public array $proposal = [];

    #[LiveProp]
    public string $error = '';

    public function __construct(
        private readonly WritingAssistant $assistant,
        private readonly NotebookQuery $notebook,
        private readonly CommandBus $commands,
    ) {
    }

    #[LiveAction]
    public function write(): void
    {
        $this->error = '';
        $this->proposal = [];

        try {
            $answer = $this->assistant->complete(self::INSTRUCTION, fn (): string => $this->matter());
        } catch (AssistantUnavailable $unavailable) {
            $this->error = $unavailable->getMessage();

            return;
        }

        $this->proposal = self::linesOf($answer);

        if ([] === $this->proposal) {
            $this->error = 'obsession.synthesis_empty';
        }
    }

    /** Garder remplace les points : c'est bien « ce qui se dégage » qu'on réécrit. */
    #[LiveAction]
    public function keep(): void
    {
        if ([] === $this->proposal) {
            return;
        }

        $obsession = $this->notebook->obsession($this->slug);

        $this->commands->dispatch(new DescribeObsession(
            $this->slug,
            $obsession?->blurb,
            $this->proposal,
        ));

        $this->proposal = [];
    }

    #[LiveAction]
    public function discard(): void
    {
        $this->proposal = [];
        $this->error = '';
    }

    /**
     * Toutes les notes de l'obsession, titre compris.
     *
     * Ouverte au dernier moment seulement : c'est la promesse du port.
     */
    private function matter(): string
    {
        $obsession = $this->notebook->obsession($this->slug);

        if (null === $obsession) {
            return '';
        }

        $notes = array_map(
            static fn ($note): string => '## '.$note->title."\n\n".$note->excerpt,
            $obsession->notes,
        );

        return implode("\n\n", $notes);
    }

    /**
     * Ce que le modèle a répondu, ramené à des lignes.
     *
     * Les puces et les numéros sont retirés : la consigne les interdit, mais un
     * modèle en remet toujours, et l'écran les rendrait deux fois.
     *
     * @return list<string>
     */
    private static function linesOf(string $answer): array
    {
        $lines = preg_split('/\R+/', trim($answer)) ?: [];

        $cleaned = array_values(array_filter(array_map(
            static fn (string $line): string => trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line) ?? $line),
            $lines,
        )));

        return \array_slice($cleaned, 0, self::MAX_LINES);
    }
}
