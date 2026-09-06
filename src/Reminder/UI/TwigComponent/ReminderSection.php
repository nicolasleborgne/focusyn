<?php

declare(strict_types=1);

namespace App\Reminder\UI\TwigComponent;

use App\Reminder\Application\Query\CalendarFeedQuery;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Section « Rappels » de l'écran des réglages, portée par Reminder :
 * notifications poussées et flux d'agenda.
 */
#[AsTwigComponent(name: 'ReminderSection', template: 'components/ReminderSection.html.twig')]
final class ReminderSection
{
    public function __construct(
        private readonly CalendarFeedQuery $feed,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $vapidPublicKey,
    ) {
    }

    /**
     * Sans clés VAPID, la ligne « notifications » n'est pas rendue du tout :
     * proposer un interrupteur qui ne peut rien faire serait pire que de ne
     * rien proposer.
     */
    public function pushAvailable(): bool
    {
        return '' !== $this->vapidPublicKey;
    }

    public function vapidPublicKey(): string
    {
        return $this->vapidPublicKey;
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
