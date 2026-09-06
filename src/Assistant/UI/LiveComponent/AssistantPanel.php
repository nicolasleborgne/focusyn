<?php

declare(strict_types=1);

namespace App\Assistant\UI\LiveComponent;

use App\Assistant\Application\Command\AskAssistant\AskAssistant;
use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Query\AssistantQuery;
use App\Assistant\Application\Query\AssistantView;
use App\Assistant\Application\Query\QuickAction;
use App\Shared\Application\Command\CommandBus;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use ValueError;

/**
 * Panneau d'assistance, sous l'éditeur.
 *
 * Terrain d'un Live Component : chaque demande part au serveur, qui seul détient
 * la clé et décide ce qui est envoyé. L'insertion du résultat dans la note,
 * elle, reste côté client — c'est l'éditeur qui possède le texte en cours.
 *
 * La sélection est reçue du client mais n'est utilisée que si le réglage le
 * demande : c'est le serveur qui tranche entre note entière et extrait.
 */
#[AsLiveComponent(name: 'AssistantPanel', template: 'components/AssistantPanel.html.twig')]
final class AssistantPanel
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $noteId = '';

    #[LiveProp(writable: true)]
    public string $prompt = '';

    #[LiveProp(writable: true)]
    public string $selection = '';

    #[LiveProp]
    public string $result = '';

    /** Ce qui a été demandé, pour l'afficher au-dessus du résultat. */
    #[LiveProp]
    public string $label = '';

    #[LiveProp]
    public string $error = '';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly AssistantQuery $assistant,
    ) {
    }

    public function settings(): AssistantView
    {
        return $this->assistant->forCurrentAccount();
    }

    /** @return list<QuickAction> */
    public function quickActions(): array
    {
        return QuickAction::cases();
    }

    #[LiveAction]
    public function run(#[LiveArg] string $action): void
    {
        try {
            $quick = QuickAction::from($action);
        } catch (ValueError) {
            return;
        }

        $this->ask($quick->instruction(), 'assistant.action.'.$quick->value);
    }

    #[LiveAction]
    public function send(): void
    {
        $prompt = trim($this->prompt);

        if ('' === $prompt) {
            return;
        }

        $this->prompt = '';
        $this->ask($prompt, 'assistant.action.asked');
    }

    #[LiveAction]
    public function dismiss(): void
    {
        $this->result = '';
        $this->label = '';
        $this->error = '';
    }

    private function ask(string $instruction, string $label): void
    {
        $this->result = '';
        $this->error = '';
        $this->label = $label;

        try {
            $answer = $this->commands->dispatch(new AskAssistant($this->noteId, $instruction, $this->selection));
            $this->result = \is_string($answer) ? $answer : '';
        } catch (AssistantRefused $refusal) {
            $this->error = $refusal->getMessage();
            $this->label = '';
        }
    }
}
