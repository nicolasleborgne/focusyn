<?php

declare(strict_types=1);

namespace App\Inbox\Application\Query;

use App\Inbox\Domain\Model\Capture;
use App\Inbox\Domain\Repository\CaptureRepository;

/**
 * Ce qu'il reste à trier.
 *
 * L'extrait est coupé ici et non dans le gabarit : c'est une décision sur le
 * contenu, pas sur la mise en page, et la couche de présentation n'a pas à
 * connaître ce qui tient sur deux lignes.
 */
final readonly class InboxQuery
{
    private const int EXCERPT_LENGTH = 180;

    public function __construct(
        private CaptureRepository $captures,
    ) {
    }

    /** @return list<CaptureView> */
    public function pending(): array
    {
        return array_map(self::view(...), $this->captures->pending());
    }

    public function count(): int
    {
        return $this->captures->count();
    }

    private static function view(Capture $capture): CaptureView
    {
        $body = $capture->body();
        $title = $capture->title()->toString();

        // Le corps répète souvent le titre — une adresse seule, une phrase
        // courte. Le redire sous lui n'apprendrait rien.
        $excerpt = str_starts_with($body, $title) ? trim(mb_substr($body, mb_strlen($title))) : $body;

        return new CaptureView(
            id: $capture->id()->toString(),
            kind: $capture->kind()->value,
            source: $capture->source()->value,
            title: $title,
            excerpt: mb_substr(trim(preg_replace('/\s+/u', ' ', $excerpt) ?? ''), 0, self::EXCERPT_LENGTH),
            capturedAt: $capture->capturedAt(),
        );
    }
}
