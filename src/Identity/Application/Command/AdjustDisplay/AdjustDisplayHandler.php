<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\AdjustDisplay;

use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Domain\Model\Accent;
use App\Identity\Domain\Model\Density;
use App\Identity\Domain\Model\MarkOpacity;
use App\Identity\Domain\Model\ProseFont;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Chaque réglage est facultatif : l'écran en modifie un à la fois, et laisser
 * les autres à null évite de les réécrire avec des valeurs relues de la page.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AdjustDisplayHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(AdjustDisplay $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        $display = $user->display();

        if (null !== $command->accent) {
            $display = $display->withAccent(Accent::from($command->accent));
        }

        if (null !== $command->proseFont) {
            $display = $display->withProseFont(ProseFont::from($command->proseFont));
        }

        if (null !== $command->density) {
            $display = $display->withDensity(Density::from($command->density));
        }

        if (null !== $command->markOpacity) {
            $display = $display->withMarkOpacity(MarkOpacity::fromFloat($command->markOpacity));
        }

        if (null !== $command->previewPane) {
            $display = $display->withPreviewPane($command->previewPane);
        }

        if ($display->equals($user->display())) {
            return;
        }

        $user->adjustDisplay($display);
        $this->users->save($user);
    }
}
