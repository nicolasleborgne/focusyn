<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command\AskAssistant;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Exception\KeyVaultUnavailable;
use App\Assistant\Application\Port\KeyVault;
use App\Assistant\Application\Provider\ProviderRegistry;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Notebook\NoteSource;
use App\Shared\Application\Privacy\ConsentGate;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Interroge le modèle sur une note.
 *
 * L'ordre des vérifications n'est pas indifférent : **le consentement d'abord**.
 * Sans lui, rien n'est lu, rien n'est déchiffré, rien ne part — et l'on ne
 * révèle même pas si une clé existe.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AskAssistantHandler
{
    private const string SYSTEM = <<<'PROMPT'
        Tu es l'assistant d'écriture de Focusyn, un carnet de synthèse personnel.
        Réponds en français, en markdown sobre, sans préambule ni conclusion,
        sans flatterie.
        PROMPT;

    public function __construct(
        private AssistantSettingsRepository $settings,
        private ProviderRegistry $providers,
        private KeyVault $vault,
        private NoteSource $notes,
        private ConsentGate $consent,
        private CurrentAccount $account,
    ) {
    }

    public function __invoke(AskAssistant $command): string
    {
        $accountId = $this->account->idOrNull() ?? throw new AssistantRefused('assistant.error.no_account');

        if (!$this->consent->allows('assistant', $accountId)) {
            throw new AssistantRefused('assistant.error.no_consent');
        }

        $settings = $this->settings->ofOwner(OwnerId::fromString($accountId));

        if (null === $settings || !$settings->isUsable()) {
            throw new AssistantRefused('assistant.error.not_configured');
        }

        $instruction = trim($command->instruction);

        if ('' === $instruction) {
            throw new AssistantRefused('assistant.error.empty_instruction');
        }

        $source = $this->sourceFor($settings->sendsWholeNote(), $command);

        $key = $settings->key();

        try {
            $plainKey = null === $key ? null : $this->vault->unseal($key);
        } catch (KeyVaultUnavailable) {
            throw new AssistantRefused('assistant.error.unsealable_key');
        }

        return $this->providers->for($settings->provider())->complete(
            $settings->model(),
            self::SYSTEM,
            $instruction."\n\n---\n\n".$source,
            $plainKey,
            $settings->baseUrl(),
        );
    }

    /**
     * Ce qui part réellement au modèle.
     *
     * Le réglage « envoyer la note entière » est appliqué **ici**, côté serveur
     * : le laisser au client reviendrait à ce qu'une page mal à jour envoie
     * toute une note qu'on avait justement demandé de ne pas envoyer.
     */
    private function sourceFor(bool $wholeNote, AskAssistant $command): string
    {
        if (!$wholeNote) {
            $selection = trim($command->selection);

            return '' === $selection
                ? throw new AssistantRefused('assistant.error.no_selection') : $selection;
        }

        return $this->notes->textOf($command->noteId)
            ?? throw new AssistantRefused('assistant.error.note_gone');
    }
}
