<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command\AdjustPrivacy;

use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\PrivacyChoices;
use App\Privacy\Domain\Model\Retention;
use App\Privacy\Domain\Model\SubjectId;
use App\Privacy\Domain\Repository\PrivacyChoicesRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AdjustPrivacyHandler
{
    public function __construct(
        private PrivacyChoicesRepository $choices,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdjustPrivacy $command): void
    {
        $subjectId = SubjectId::fromString($command->subjectId);
        $now = $this->clock->now();

        // La fiche naît au premier choix : tant que personne n'a rien décidé,
        // il n'y a rien à conserver.
        $choices = $this->choices->ofSubject($subjectId) ?? PrivacyChoices::forSubject($subjectId, $now);

        if (null !== $command->consent && null !== $command->granted) {
            $consent = Consent::from($command->consent);
            $command->granted ? $choices->grant($consent, $now) : $choices->withdraw($consent, $now);
        }

        if (null !== $command->retention) {
            $choices->keepFor(Retention::from($command->retention), $now);
        }

        $this->choices->save($choices);
    }
}
