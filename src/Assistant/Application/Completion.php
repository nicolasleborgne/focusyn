<?php

declare(strict_types=1);

namespace App\Assistant\Application;

use App\Assistant\Application\Exception\AssistantRefused;
use App\Assistant\Application\Exception\KeyVaultUnavailable;
use App\Assistant\Application\Port\AllowedLocalAddresses;
use App\Assistant\Application\Port\KeyVault;
use App\Assistant\Application\Provider\ProviderRegistry;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Billing\Entitlements;
use App\Shared\Application\Privacy\ConsentGate;
use Closure;

/**
 * Tout ce qu'il faut vérifier avant qu'un mot ne sorte, en un seul endroit.
 *
 * **L'ordre n'est pas indifférent, et c'est pourquoi il n'est écrit qu'ici** :
 * le consentement d'abord — sans lui rien n'est lu, rien n'est déchiffré, et
 * l'on ne révèle même pas si une clé existe. Le palier ensuite : rien ne doit
 * être déchiffré pour une organisation qui n'y a pas droit.
 *
 * Chaque appelant qui referait cette séquence pour son compte finirait par en
 * intervertir deux lignes, un jour, sans que rien ne le signale.
 */
final readonly class Completion
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
        private ConsentGate $consent,
        private CurrentAccount $account,
        private Entitlements $entitlements,
        private AllowedLocalAddresses $localAddresses,
    ) {
    }

    /**
     * La matière est **paresseuse**, et ce n'est pas un détail.
     *
     * `$source` n'est appelée qu'une fois toutes les vérifications passées :
     * sans consentement, la note n'est même pas lue. La passer déjà lue
     * reviendrait à ouvrir le carnet avant de savoir si on en a le droit — et
     * l'appelant, lui, ne peut pas connaître l'ordre des vérifications.
     *
     * @param Closure(): string $source
     *
     * @throws AssistantRefused
     */
    public function of(string $instruction, Closure $source): string
    {
        $accountId = $this->account->idOrNull() ?? throw new AssistantRefused('assistant.error.no_account');

        if (!$this->consent->allows('assistant', $accountId)) {
            throw new AssistantRefused('assistant.error.no_consent');
        }

        if (!$this->entitlements->allowsAssistant()) {
            throw new AssistantRefused('billing.limit.assistant');
        }

        $settings = $this->settings->ofOwner(OwnerId::fromString($accountId));

        if (null === $settings || !$settings->isUsable()) {
            throw new AssistantRefused('assistant.error.not_configured');
        }

        $asked = trim($instruction);

        if ('' === $asked) {
            throw new AssistantRefused('assistant.error.empty_instruction');
        }

        // Vérifiée une seconde fois, à l'usage. L'adresse a été contrôlée le
        // jour où elle a été saisie ; retirer une adresse de la liste doit la
        // faire cesser d'être appelée, sans qu'il faille aller nettoyer les
        // réglages de chaque compte.
        $baseUrl = $settings->baseUrl();

        if (null !== $baseUrl && !$this->localAddresses->permits($baseUrl)) {
            throw new AssistantRefused('assistant.error.address_refused');
        }

        $key = $settings->key();

        try {
            $plainKey = null === $key ? null : $this->vault->unseal($key);
        } catch (KeyVaultUnavailable) {
            throw new AssistantRefused('assistant.error.unsealable_key');
        }

        return $this->providers->for($settings->provider())->complete(
            $settings->model(),
            self::SYSTEM,
            $asked."\n\n---\n\n".$source(),
            $plainKey,
            $baseUrl,
        );
    }

    /** Le réglage « envoyer la note entière » : lu ici, appliqué par l'appelant. */
    public function sendsWholeNote(): bool
    {
        $accountId = $this->account->idOrNull();

        if (null === $accountId) {
            return false;
        }

        return $this->settings->ofOwner(OwnerId::fromString($accountId))?->sendsWholeNote() ?? false;
    }
}
