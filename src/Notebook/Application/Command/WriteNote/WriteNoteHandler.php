<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\WriteNote;

use App\Notebook\Domain\Model\AuthorId;
use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteBody;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\NoteTitle;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Billing\Entitlements;
use App\Shared\Application\Billing\PlanLimitReached;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class WriteNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
        private CurrentTenant $tenant,
        private CurrentAccount $account,
        private Entitlements $entitlements,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(WriteNote $command): NoteId
    {
        // Le plafond arrête l'écriture, jamais la lecture : ce qui est déjà
        // écrit reste lisible et exportable, quel que soit le palier.
        $allowance = $this->entitlements->noteAllowance();

        if (null !== $allowance && $this->notes->count() >= $allowance) {
            throw PlanLimitReached::notes($allowance);
        }

        $note = Note::write(
            NoteId::generate(),
            $this->tenant->id(),
            AuthorId::fromString((string) $this->account->idOrNull()),
            NoteTitle::fromString($command->title),
            NoteBody::fromString($command->body),
            array_map(ObsessionName::fromString(...), $command->obsessions),
            $this->clock->now(),
        );

        $this->notes->save($note);

        return $note->id();
    }
}
