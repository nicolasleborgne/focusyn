<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\DescribeObsession;

use App\Notebook\Application\Exception\ObsessionNotFound;
use App\Notebook\Domain\Model\Obsession;
use App\Notebook\Domain\Model\ObsessionBlurb;
use App\Notebook\Domain\Model\ObsessionId;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Model\ObsessionPoint;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Notebook\Domain\Repository\ObsessionRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DescribeObsessionHandler
{
    public function __construct(
        private ObsessionRepository $obsessions,
        private NoteRepository $notes,
        private CurrentTenant $tenant,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DescribeObsession $command): void
    {
        $blurb = null === $command->blurb || '' === trim($command->blurb)
            ? null
            : ObsessionBlurb::fromString($command->blurb);

        $points = array_values(array_map(
            ObsessionPoint::fromString(...),
            array_filter($command->points, static fn (string $text): bool => '' !== trim($text)),
        ));

        $now = $this->clock->now();
        $obsession = $this->obsessions->ofSlug($command->slug);

        if (null === $obsession) {
            $obsession = Obsession::describe(
                ObsessionId::generate(),
                $this->tenant->id(),
                $this->nameFor($command->slug),
                $blurb,
                $points,
                $now,
            );
        } else {
            $obsession->rewriteBlurb($blurb, $now);
            $obsession->replacePoints($points, $now);
        }

        // Une fiche qui ne dit plus rien est retirée : mieux vaut une absence
        // franche qu'une ligne vide qui laisse croire à une description.
        if (!$obsession->saysSomething()) {
            $this->obsessions->remove($obsession);

            return;
        }

        $this->obsessions->save($obsession);
    }

    /**
     * Le nom écrit vient des notes qui portent l'obsession : c'est là qu'il a
     * été saisi.
     */
    private function nameFor(string $slug): ObsessionName
    {
        foreach ($this->notes->obsessionCounts() as $obsession) {
            if ($obsession['slug'] === $slug) {
                return ObsessionName::fromString($obsession['name']);
            }
        }

        throw ObsessionNotFound::withSlug($slug);
    }
}
