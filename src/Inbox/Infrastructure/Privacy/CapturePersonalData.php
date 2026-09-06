<?php

declare(strict_types=1);

namespace App\Inbox\Infrastructure\Privacy;

use App\Inbox\Domain\Model\Capture;
use App\Inbox\Domain\Repository\CaptureRepository;
use App\Shared\Application\Privacy\PersonalDataContributor;

/**
 * La boîte fait partie des données du compte.
 *
 * Ce qui n'est pas encore trié n'en est pas moins écrit : l'oublier de l'export
 * laisserait dehors ce qu'on a justement mis de côté pour y revenir, et
 * l'oublier de l'effacement laisserait derrière soi des adresses partagées
 * qu'on croyait parties avec le reste.
 */
final readonly class CapturePersonalData implements PersonalDataContributor
{
    public function __construct(
        private CaptureRepository $captures,
    ) {
    }

    public function section(): string
    {
        return 'boite';
    }

    public function export(): array
    {
        return array_map(
            static fn (Capture $capture): array => [
                'titre' => $capture->title()->toString(),
                'texte' => $capture->body(),
                'nature' => $capture->kind()->value,
                'provenance' => $capture->source()->value,
                'captureLe' => $capture->capturedAt()->format(\DATE_ATOM),
            ],
            $this->captures->pending(),
        );
    }

    public function erase(): void
    {
        foreach ($this->captures->pending() as $capture) {
            $this->captures->remove($capture);
        }
    }
}
