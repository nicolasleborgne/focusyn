<?php

declare(strict_types=1);

namespace App\Reminder\UI\TwigComponent;

use App\Reminder\Application\Query\CalendarFeedQuery;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Section « Agenda » de l'écran des réglages, portée par Reminder.
 */
#[AsTwigComponent(name: 'CalendarFeedSection', template: 'components/CalendarFeedSection.html.twig')]
final class CalendarFeedSection
{
    public function __construct(
        private readonly CalendarFeedQuery $feed,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * L'adresse complète, absolue : elle est destinée à être recopiée dans un
     * autre logiciel, où un chemin relatif ne voudrait rien dire.
     */
    public function url(): ?string
    {
        $token = $this->feed->tokenOfCurrentOrganization();

        return null === $token ? null : $this->urls->generate(
            'calendar_feed',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
