<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Shell;

use App\Shared\Application\Shell\ObsessionSummary;
use App\Shared\Application\Shell\RoutineSummary;
use App\Shared\Application\Shell\ShellView;
use App\Shared\Application\Shell\TaskListSummary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShellView::class)]
final class ShellViewTest extends TestCase
{
    public function testItDerivesTheAccountInitialFromTheEmail(): void
    {
        $view = self::view(accountEmail: 'nicolas@focusyn.fr');

        self::assertSame('N', $view->accountInitial());
    }

    #[DataProvider('emailsWithoutUsableInitial')]
    public function testItFallsBackWhenTheEmailCannotYieldAnInitial(string $email): void
    {
        self::assertSame('?', self::view(accountEmail: $email)->accountInitial());
    }

    /** @return iterable<string, array{string}> */
    public static function emailsWithoutUsableInitial(): iterable
    {
        yield 'chaîne vide' => [''];
        yield 'espaces seuls' => ['   '];
    }

    public function testTheAccountInitialIsAlwaysUppercase(): void
    {
        self::assertSame('É', self::view(accountEmail: 'éditeur@focusyn.fr')->accountInitial());
    }

    public function testItCountsOpenTasksAcrossEveryList(): void
    {
        $view = self::view(taskLists: [
            new TaskListSummary('Cette semaine', 'cette-semaine', 2),
            new TaskListSummary('Essais café', 'essais-cafe', 3),
            new TaskListSummary('Terminée', 'terminee', 0),
        ]);

        self::assertSame(5, $view->openTaskCount());
    }

    public function testItSumsToZeroWithoutAnyList(): void
    {
        self::assertSame(0, self::view()->openTaskCount());
    }

    public function testItExposesTheNumberOfObsessions(): void
    {
        $view = self::view(obsessions: [
            new ObsessionSummary('Sommeil', 'sommeil', 2),
            new ObsessionSummary('Café', 'cafe', 1),
        ]);

        self::assertSame(2, $view->obsessionCount());
    }

    /**
     * @param list<ObsessionSummary> $obsessions
     * @param list<TaskListSummary>  $taskLists
     * @param list<RoutineSummary>   $routines
     */
    private static function view(
        string $accountEmail = 'moi@focusyn.fr',
        int $noteCount = 0,
        array $obsessions = [],
        array $taskLists = [],
        array $routines = [],
        int $inboxCount = 0,
    ): ShellView {
        return new ShellView($accountEmail, $noteCount, $obsessions, $taskLists, $routines, $inboxCount);
    }
}
