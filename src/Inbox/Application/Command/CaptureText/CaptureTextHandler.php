<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\CaptureText;

use App\Inbox\Domain\Model\Capture;
use App\Inbox\Domain\Model\CaptureId;
use App\Inbox\Domain\Model\CaptureSource;
use App\Inbox\Domain\Repository\CaptureRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Jeter quelque chose dans la boîte.
 *
 * Aucun plafond ici : la boîte n'est pas le carnet, rien n'y est encore écrit.
 * Le palier se vérifiera au tri, quand la capture deviendra une note — refuser
 * l'entrée reviendrait à perdre ce qui a été partagé depuis une autre
 * application, sans que personne ne l'apprenne.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CaptureTextHandler
{
    public function __construct(
        private CaptureRepository $captures,
        private CurrentTenant $tenant,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CaptureText $command): CaptureId
    {
        $capture = Capture::receive(
            CaptureId::generate(),
            $this->tenant->id(),
            $command->text,
            CaptureSource::from($command->source),
            $this->clock->now(),
        );

        $this->captures->save($capture);

        return $capture->id();
    }
}
