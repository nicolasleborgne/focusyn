<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\RotateCalendarFeed;

use App\Reminder\Application\Port\FeedTokenGenerator;
use App\Reminder\Domain\Model\CalendarFeed;
use App\Reminder\Domain\Model\CalendarFeedId;
use App\Reminder\Domain\Repository\CalendarFeedRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RotateCalendarFeedHandler
{
    public function __construct(
        private CalendarFeedRepository $feeds,
        private FeedTokenGenerator $tokens,
        private CurrentTenant $tenant,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RotateCalendarFeed $command): string
    {
        $organization = $this->tenant->id();
        $feed = $this->feeds->ofOrganization($organization);
        $now = $this->clock->now();

        if (null === $feed) {
            $feed = CalendarFeed::open(CalendarFeedId::generate(), $organization, $this->tokens->generate(), $now);
        } else {
            $feed->rotate($this->tokens->generate(), $now);
        }

        $this->feeds->save($feed);

        return $feed->token()->toString();
    }
}
