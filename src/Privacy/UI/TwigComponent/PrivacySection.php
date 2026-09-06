<?php

declare(strict_types=1);

namespace App\Privacy\UI\TwigComponent;

use App\Privacy\Application\Query\PrivacyQuery;
use App\Privacy\Application\Query\PrivacyView;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\Retention;
use App\Privacy\Domain\Model\SubjectId;
use App\Shared\Application\Account\CurrentAccount;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Section « Données personnelles » de l'écran de réglages.
 *
 * Un composant plutôt qu'un bloc de gabarit rendu par le contrôleur des
 * réglages : celui-ci appartient à Identity, qui n'a pas le droit de connaître
 * Privacy. Le composant, lui, est porté par Privacy et se contente d'être
 * appelé.
 */
#[AsTwigComponent(name: 'PrivacySection', template: 'components/PrivacySection.html.twig')]
final class PrivacySection
{
    public function __construct(
        private readonly PrivacyQuery $privacy,
        private readonly CurrentAccount $account,
        private readonly RequestStack $requests,
    ) {
    }

    public function view(): ?PrivacyView
    {
        $subjectId = $this->account->idOrNull();

        return null === $subjectId ? null : $this->privacy->forSubject(SubjectId::fromString($subjectId));
    }

    /**
     * L'effacement a-t-il déjà été demandé une première fois ?
     *
     * Le bouton doit dire ce qu'il va faire : « Effacer » au premier clic,
     * « Confirmer » au second.
     */
    public function erasureArmed(): bool
    {
        return true === $this->requests->getSession()->get('privacy.erase_armed');
    }

    /** @return list<Consent> */
    public function consents(): array
    {
        return Consent::cases();
    }

    /** @return list<Retention> */
    public function retentions(): array
    {
        return Retention::cases();
    }
}
