<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command\AdjustAssistant;

use App\Assistant\Application\Port\AllowedLocalAddresses;
use App\Assistant\Application\Port\KeyVault;
use App\Assistant\Domain\Model\AssistantSettings;
use App\Assistant\Domain\Model\OwnerId;
use App\Assistant\Domain\Model\Provider;
use App\Assistant\Domain\Repository\AssistantSettingsRepository;
use App\Shared\Application\Account\CurrentAccount;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Un seul cas d'usage pour tout l'écran : chaque formulaire n'envoie que le
 * champ qu'il modifie, les autres restent nuls.
 *
 * L'ordre compte : le fournisseur d'abord, puisqu'en changer efface la clé et
 * le modèle. Envoyer les deux dans la même requête donnerait sinon un résultat
 * qui dépend de l'ordre des `if`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AdjustAssistantHandler
{
    public function __construct(
        private AssistantSettingsRepository $settings,
        private KeyVault $vault,
        private CurrentAccount $account,
        private AllowedLocalAddresses $localAddresses,
    ) {
    }

    public function __invoke(AdjustAssistant $command): void
    {
        $owner = OwnerId::fromString(
            $this->account->idOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.'),
        );

        $settings = $this->settings->ofOwner($owner) ?? AssistantSettings::forOwner($owner);

        if (null !== $command->provider) {
            $provider = Provider::from($command->provider);

            // Refuser le passage, et pas seulement l'adresse : sans adresse
            // autorisée, un fournisseur local laisserait le compte dans un
            // état qu'aucune saisie ne peut plus rendre utilisable.
            if ($provider->isLocal() && [] === $this->localAddresses->all()) {
                throw new InvalidArgumentException('Aucune adresse locale n\'est autorisée sur cette instance.');
            }

            $settings->switchTo($provider);
        }

        if (null !== $command->apiKey) {
            $key = trim($command->apiKey);

            // Vider le champ retire la clé : c'est le seul moyen de la
            // supprimer sans supprimer le reste des réglages.
            '' === $key ? $settings->forgetKey() : $settings->useKey($this->vault->seal($key));
        }

        if (null !== $command->model) {
            $settings->chooseModel($command->model);
        }

        if (null !== $command->baseUrl && '' !== trim($command->baseUrl)) {
            // La liste appartient à l'exploitant, jamais au compte : une
            // adresse libre ferait du serveur un relais vers son propre
            // réseau — une requête forgée côté serveur, depuis un écran de
            // réglages.
            if (!$this->localAddresses->permits($command->baseUrl)) {
                throw new InvalidArgumentException('Cette adresse n\'est pas autorisée sur cette instance.');
            }

            $settings->reachableAt($command->baseUrl);
        }

        if (null !== $command->wholeNote) {
            $command->wholeNote ? $settings->sendWholeNote() : $settings->sendSelectionOnly();
        }

        $this->settings->save($settings);
    }
}
