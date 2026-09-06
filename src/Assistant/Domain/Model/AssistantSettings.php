<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use InvalidArgumentException;

/**
 * Le branchement d'une personne sur le fournisseur de son choix.
 *
 * **Ces réglages suivent la personne, pas l'organisation** : c'est sa clé,
 * c'est sa facture. Un agrégat par compte, créé au premier réglage.
 *
 * La clé n'est jamais ici en clair : l'agrégat manipule un `SealedKey` et
 * ignore comment on le descelle.
 */
final class AssistantSettings extends AggregateRoot
{
    private function __construct(
        private readonly OwnerId $ownerId,
        private Provider $provider,
        private ?SealedKey $key,
        private ?string $model,
        private ?string $baseUrl,
        private bool $wholeNote,
    ) {
    }

    public static function forOwner(OwnerId $ownerId): self
    {
        // La maquette envoie la note entière par défaut : c'est ce qui rend les
        // actions rapides utiles sans rien sélectionner.
        return new self($ownerId, Provider::Anthropic, null, null, null, true);
    }

    public function ownerId(): OwnerId
    {
        return $this->ownerId;
    }

    public function provider(): Provider
    {
        return $this->provider;
    }

    public function key(): ?SealedKey
    {
        return $this->key;
    }

    public function model(): string
    {
        return $this->model ?? $this->provider->defaultModel();
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function sendsWholeNote(): bool
    {
        return $this->wholeNote;
    }

    /** Un fournisseur local n'a pas de clé à donner ; les autres, si. */
    public function isUsable(): bool
    {
        return $this->provider->isLocal()
            ? null !== $this->baseUrl
            : null !== $this->key;
    }

    /**
     * Changer de fournisseur remet à zéro ce qui lui était propre.
     *
     * Une clé Anthropic n'ouvre rien chez OpenAI, et « claude-haiku » n'y
     * existe pas : les garder ne ferait qu'échouer plus tard, à un endroit où
     * l'on ne comprendrait plus pourquoi.
     */
    public function switchTo(Provider $provider): void
    {
        if ($provider === $this->provider) {
            return;
        }

        $this->provider = $provider;
        $this->key = null;
        $this->model = null;
        $this->baseUrl = null;
    }

    public function useKey(SealedKey $key): void
    {
        $this->key = $key;
    }

    public function forgetKey(): void
    {
        $this->key = null;
    }

    public function chooseModel(string $model): void
    {
        if (!\in_array($model, $this->provider->models(), true)) {
            throw new InvalidArgumentException(\sprintf('« %s » n\'est pas un modèle de ce fournisseur.', $model));
        }

        $this->model = $model;
    }

    /**
     * Seul un fournisseur local prend une adresse : laisser changer celle d'un
     * fournisseur hébergé permettrait de détourner la clé vers un serveur
     * choisi par un tiers.
     */
    public function reachableAt(string $baseUrl): void
    {
        if (!$this->provider->isLocal()) {
            throw new InvalidArgumentException('Seul un fournisseur local prend une adresse.');
        }

        $trimmed = trim($baseUrl);

        if (1 !== preg_match('#^https?://[^\s]+$#', $trimmed)) {
            throw new InvalidArgumentException('Cette adresse n\'est pas utilisable.');
        }

        $this->baseUrl = rtrim($trimmed, '/');
    }

    public function sendWholeNote(): void
    {
        $this->wholeNote = true;
    }

    public function sendSelectionOnly(): void
    {
        $this->wholeNote = false;
    }
}
