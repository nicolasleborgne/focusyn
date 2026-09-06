<?php

declare(strict_types=1);

namespace App\Assistant\UI\TwigComponent;

use App\Assistant\Application\Query\AssistantQuery;
use App\Assistant\Application\Query\AssistantView;
use App\Assistant\Domain\Model\Provider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Section « Assistant » de l'écran des réglages, portée par Assistant.
 */
#[AsTwigComponent(name: 'AssistantSection', template: 'components/AssistantSection.html.twig')]
final class AssistantSection
{
    public function __construct(
        private readonly AssistantQuery $assistant,
    ) {
    }

    public function view(): AssistantView
    {
        return $this->assistant->forCurrentAccount();
    }

    /** @return list<Provider> */
    public function providers(): array
    {
        return Provider::cases();
    }
}
